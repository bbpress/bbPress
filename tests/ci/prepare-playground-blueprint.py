import json
from pathlib import Path
import shutil
import sys


if len(sys.argv) != 4:
    sys.exit("Usage: prepare-playground-blueprint.py BLUEPRINT PLUGIN_ZIP OUTPUT_DIR")

blueprint_file = Path(sys.argv[1])
plugin_zip = Path(sys.argv[2])
output_dir = Path(sys.argv[3])

blueprint = json.loads(blueprint_file.read_text())
plugin_steps = [
    step
    for step in blueprint.get("steps", [])
    if step.get("step") == "installPlugin"
    and step.get("pluginData", {}).get("resource") == "wordpress.org/plugins"
    and step.get("pluginData", {}).get("slug") == "bbpress"
]

if len(plugin_steps) != 1:
    sys.exit("The blueprint must install bbPress from the Plugin Directory once.")

output_dir.mkdir(parents=True, exist_ok=True)
shutil.copyfile(plugin_zip, output_dir / "bbpress.zip")
plugin_steps[0]["pluginData"] = {
    "resource": "bundled",
    "path": "/bbpress.zip",
}
(output_dir / "blueprint.json").write_text(json.dumps(blueprint, indent="\t") + "\n")
