#!/usr/bin/env bash

set -euo pipefail

usage() {
	echo "Usage: $0 CANDIDATE_DIR PLUGIN_SVN_WORKING_COPY VERSION SERIES BLUEPRINT_FILE" >&2
	exit 2
}

fail() {
	echo "Error: $*" >&2
	exit 1
}

[[ $# -eq 5 ]] || usage

candidate_dir="$1"
working_copy="$2"
version="$3"
series="$4"
blueprint_file="$5"

[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "Version must use the x.y.z format."
[[ "$series" =~ ^[0-9]+\.[0-9]+$ ]] || fail "Series must use the x.y format."
[[ "$version" == "$series".* ]] || fail "Version $version does not belong to series $series."
[[ -f "$blueprint_file" ]] || fail "Blueprint file is not available."

candidate_dir="$(cd "$candidate_dir" && pwd -P)"
working_copy="$(cd "$working_copy" && pwd -P)"
blueprint_file="$(cd "$(dirname "$blueprint_file")" && pwd -P)/$(basename "$blueprint_file")"

[[ "$candidate_dir" != "/" ]] || fail "Candidate directory cannot be the filesystem root."
[[ "$working_copy" != "/" ]] || fail "Working copy cannot be the filesystem root."
[[ -d "$working_copy/.svn" ]] || fail "$working_copy is not a Subversion working copy."
python3 -m json.tool "$blueprint_file" >/dev/null || fail "Blueprint is not valid JSON."

case "$candidate_dir/" in "$working_copy/"*) fail "Candidate cannot be inside the working copy." ;; esac
case "$working_copy/" in "$candidate_dir/"*) fail "Working copy cannot be inside the candidate." ;; esac
[[ -z "$(find "$candidate_dir" -type l -print)" ]] || fail "Candidate contains symbolic links."

expected_url="${BBPRESS_PLUGIN_SVN_URL:-https://plugins.svn.wordpress.org/bbpress}"
expected_url="${expected_url%/}"
actual_url="$(svn info --show-item url "$working_copy")"
[[ "${actual_url%/}" == "${expected_url%/}" ]] || fail "Expected $expected_url, found $actual_url."

[[ -z "$(svn status --no-ignore "$working_copy")" ]] || fail "Plugin SVN working copy is not clean."
[[ -f "$candidate_dir/bbpress.php" ]] || fail "Candidate is missing bbpress.php."
[[ -f "$candidate_dir/readme.txt" ]] || fail "Candidate is missing readme.txt."
[[ -s "$candidate_dir/bbpress.pot" ]] || fail "Candidate is missing a non-empty bbpress.pot."
[[ ! -e "$candidate_dir/tests" ]] || fail "Candidate unexpectedly contains tests."
for development_path in .github .git .svn Gruntfile.js package.json package-lock.json composer.json composer.lock; do
	[[ ! -e "$candidate_dir/$development_path" ]] || fail "Candidate unexpectedly contains $development_path."
done
escaped_version="${version//./\\.}"
grep -Eq "^[[:space:]]*\*[[:space:]]+Version:[[:space:]]+$escaped_version$" "$candidate_dir/bbpress.php" || fail "Plugin header does not match $version."
grep -Eq "^Stable tag:[[:space:]]+$escaped_version$" "$candidate_dir/readme.txt" || fail "Stable tag does not match $version."

trunk="$working_copy/trunk"
branch="$working_copy/branches/$series"
tag="$working_copy/tags/$version"
assets="$working_copy/assets"

[[ -d "$trunk" ]] || fail "Plugin SVN trunk is not available."
[[ -d "$branch" ]] || fail "Plugin SVN branch $series is not available."
[[ -d "$assets" ]] || fail "Plugin SVN assets are not available."
[[ ! -e "$tag" ]] || fail "Plugin SVN tag $version already exists."

svn list --xml "$expected_url/tags" | python3 -c '
import sys, xml.etree.ElementTree as ET
if any(entry.findtext("name") == sys.argv[1] for entry in ET.parse(sys.stdin).findall(".//entry")):
    sys.exit("The deployment tag already exists in the repository.")
' "$version"

# Require complete, unswitched deployment trees before synchronizing files.
for target in "$trunk" "$branch" "$assets"; do
	[[ "$(svn info --show-item url "$target")" == "$expected_url/${target#"$working_copy/"}" ]] || fail "Unexpected deployment target URL."
	[[ "$(svn info --show-item depth "$target")" == "infinity" ]] || fail "Deployment target is sparse."
	svn info --xml --depth infinity "$target" | python3 -c '
import sys, xml.etree.ElementTree as ET
root = ET.parse(sys.stdin)
for entry in root.findall("entry"):
    if entry.get("kind") == "dir" and entry.findtext("wc-info/depth") != "infinity":
        sys.exit("Deployment target contains a sparse directory.")
'
done

sync_tree() {
	local target="$1"

	rsync -rc --delete --exclude=.svn "$candidate_dir/" "$target/"
	svn add --force "$target" >/dev/null

	svn status --xml "$target" | python3 -c '
import subprocess, sys, xml.etree.ElementTree as ET
for entry in ET.parse(sys.stdin).findall(".//entry"):
    if entry.find("wc-status").get("item") == "missing":
        subprocess.run(["svn", "rm", "--force", "--", entry.get("path") + "@"], check=True, stdout=subprocess.DEVNULL)
'
}

sync_tree "$trunk"
sync_tree "$branch"
svn copy "$branch" "$tag" >/dev/null

mkdir -p "$assets/blueprints"
cp "$blueprint_file" "$assets/blueprints/blueprint.json"
svn add --force --parents "$assets/blueprints/blueprint.json" >/dev/null

diff -qr --exclude=.svn "$candidate_dir" "$trunk" >/dev/null || fail "Staged trunk differs from the candidate."
diff -qr --exclude=.svn "$candidate_dir" "$branch" >/dev/null || fail "Staged branch differs from the candidate."
diff -qr --exclude=.svn "$candidate_dir" "$tag" >/dev/null || fail "Staged tag differs from the candidate."
cmp "$blueprint_file" "$assets/blueprints/blueprint.json" >/dev/null || fail "Staged blueprint differs from canonical source."

( cd "$working_copy" && svn status && svn diff )
