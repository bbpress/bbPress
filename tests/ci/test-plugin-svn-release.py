from pathlib import Path
import subprocess as sp, tempfile, os, shutil

root = Path(tempfile.mkdtemp(prefix="bbp-deploy-review-"))
helper = Path(__file__).resolve().parents[2] / "bin/prepare-plugin-svn-release.sh"


def run(*args, **kw):
    return sp.run(
        args, text=True, stdout=sp.PIPE, stderr=sp.STDOUT, check=True, **kw
    ).stdout


run("svnadmin", "create", str(root / "repo"))
seed = root / "seed"
for name in ["trunk", "branches/2.6", "tags", "assets"]:
    (seed / name).mkdir(parents=True)
for name in ["trunk", "branches/2.6"]:
    (seed / name / "old file.txt").write_text("delete me")
    (seed / name / "old-directory").mkdir()
    (seed / name / "old-directory" / "nested.txt").write_text("delete directory")
(seed / "assets" / "banner-1544x500.png").write_text("preserve asset")
url = (root / "repo").as_uri()
run("svn", "import", "-q", str(seed), url, "-m", "Fixture.")
candidate = root / "candidate"
candidate.mkdir()
(candidate / "bbpress.php").write_text("<?php\n * Version: 2.6.16\n")
(candidate / "readme.txt").write_text("Stable tag: 2.6.16\n")
(candidate / "bbpress.pot").write_text('msgid "test"\n')
(candidate / "new file.txt").write_text("new")
blueprint = root / "blueprint.json"
blueprint.write_text('{"landingPage":"/forums/"}\n')


def case(name, setup=None, good=False):
    wc = root / name
    run("svn", "checkout", "-q", url, str(wc))
    if setup:
        setup(wc)
    before = run("svn", "status", str(wc))
    result = sp.run(
        [str(helper), str(candidate), str(wc), "2.6.16", "2.6", str(blueprint)],
        env={**os.environ, "BBPRESS_PLUGIN_SVN_URL": url},
        text=True,
        stdout=sp.PIPE,
        stderr=sp.STDOUT,
    )
    if good:
        assert result.returncode == 0, result.stdout
        for target in ["trunk", "branches/2.6", "tags/2.6.16"]:
            assert sorted(
                p.relative_to(wc / target).as_posix() for p in (wc / target).rglob("*")
            ) == sorted(
                p.relative_to(candidate).as_posix() for p in candidate.rglob("*")
            )
            for p in candidate.iterdir():
                assert p.read_bytes() == (wc / target / p.name).read_bytes()
        assert blueprint.read_bytes() == (
            wc / "assets/blueprints/blueprint.json"
        ).read_bytes()
        assert (wc / "assets/banner-1544x500.png").read_text() == "preserve asset"
        run("svn", "commit", "-q", str(wc), "-m", "Fixture deployment.")
        fresh = root / "export"
        run("svn", "export", "-q", url + "/tags/2.6.16", str(fresh))
        for p in candidate.iterdir():
            assert p.read_bytes() == (fresh / p.name).read_bytes()
    else:
        assert result.returncode != 0, name
        assert before == run("svn", "status", str(wc)), (
            name + " mutated before rejection"
        )
    print("PASS", name)


case("dirty", lambda w: (w / "untracked").write_text("preserve"))
case("sparse", lambda w: run("svn", "update", "--set-depth", "empty", str(w / "trunk")))
case("sparse-assets", lambda w: run("svn", "update", "--set-depth", "empty", str(w / "assets")))
case(
    "nested-sparse",
    lambda w: run(
        "svn", "update", "--set-depth", "empty", str(w / "trunk/old-directory")
    ),
)
(candidate / "bbpress.php").write_text("<?php\n * Version: 2x6x16\n")
case("invalid-version")
(candidate / "bbpress.php").write_text("<?php\n * Version: 2.6.16\n")
blueprint.write_text("{")
case("invalid-blueprint")
blueprint.write_text('{"landingPage":"/forums/"}\n')
(candidate / "link").symlink_to("/private/tmp")
case("symlink")
(candidate / "link").unlink()
case("success-with-deletions", good=True)
case("existing-tag")
print("Fixture:", root)
