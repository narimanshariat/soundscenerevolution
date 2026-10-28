"""Record the size and duration of every MP3 in the raw uploads.

The importer uses this to fill in podPress records that lack a size or duration.

    docker compose --profile tools run --rm --entrypoint python whisper scripts/probe-audio.py /raw/uploads/wp-content/uploads scripts/audio-probe.json
"""
import json
import os
import sys

import av

root, out = sys.argv[1:]
probe = {}
for dirpath, _, files in os.walk(root):
    for name in sorted(files):
        if not name.lower().endswith(".mp3"):
            continue
        path = os.path.join(dirpath, name)
        with av.open(path) as container:
            seconds = container.duration / av.time_base if container.duration else None
        probe[os.path.relpath(path, root)] = {
            "size": os.path.getsize(path),
            "duration": round(seconds) if seconds else None,
        }

with open(out, "w") as f:
    json.dump(dict(sorted(probe.items())), f, indent=1)
    f.write("\n")
print(f"probed {len(probe)} files")
