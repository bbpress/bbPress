#!/usr/bin/env bash

# Install the exact candidate ZIP in a disposable WordPress site.
set -euo pipefail

: "${BBPRESS_SMOKE_DB_PASSWORD:?Set the disposable database password.}"

[[ $# -eq 2 ]] || { echo "Usage: $0 CANDIDATE_ZIP EMPTY_WORDPRESS_DIRECTORY" >&2; exit 2; }
candidate_zip="$(cd "$(dirname "$1")" && pwd -P)/$(basename "$1")"
wp_root="$2"
script_dir="$(cd "$(dirname "$0")" && pwd -P)"
port="${BBPRESS_SMOKE_PORT:-8890}"
[[ "$port" =~ ^[0-9]+$ ]] || exit 2
[[ -f "$candidate_zip" ]] || exit 2
mkdir -p "$wp_root"
[[ -z "$(ls -A "$wp_root")" ]] || { echo "WordPress directory must be empty." >&2; exit 1; }
wp_root="$(cd "$wp_root" && pwd -P)"

wp core download --path="$wp_root"
printf '%s\n' "${BBPRESS_SMOKE_DB_PASSWORD}" | wp config create --prompt=dbpass --path="$wp_root" --dbname="${BBPRESS_SMOKE_DB_NAME:-wordpress_smoke}" --dbuser="${BBPRESS_SMOKE_DB_USER:-root}" --dbhost="${BBPRESS_SMOKE_DB_HOST:-127.0.0.1}"
openssl rand -base64 24 | wp core install --prompt=admin_password --path="$wp_root" --url="http://127.0.0.1:$port" --title="bbPress Smoke Test" --admin_user=admin --admin_email=admin@example.com
wp theme install twentytwentyone --path="$wp_root" --activate
wp plugin install "$candidate_zip" --path="$wp_root" --activate
wp plugin is-active bbpress --path="$wp_root"
forum_url=$(wp eval-file "$script_dir/../tests/smoke/cli.php" --path="$wp_root")
[[ "$forum_url" == "http://127.0.0.1:$port/"* ]] || { echo "Unexpected forum URL." >&2; exit 1; }

wp server --path="$wp_root" --host=127.0.0.1 --port="$port" >"$wp_root/server.log" 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT
for _attempt in {1..30}; do
	if curl --fail --silent "$forum_url" --output "$wp_root/forum.html"; then
		break
	fi
	sleep 1
done
curl --fail --silent --show-error "$forum_url" --output "$wp_root/forum.html"
grep -q 'Smoke Forum' "$wp_root/forum.html"
grep -q 'Smoke Topic' "$wp_root/forum.html"
printf 'Package smoke test passed: activation, forum/topic/reply counts, and front-end rendering.\n'
