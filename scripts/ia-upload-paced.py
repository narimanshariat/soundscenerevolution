"""Uploads scripts/ia-upload.csv to archive.org one item at a time, pausing
between items, and stops at the first error. archive.org flags new accounts
that create many items quickly, so this is gentler than `ia upload --spreadsheet`.
Items whose files are already there at the right size are skipped, so it can be
re-run at any point. Run in the ia container:

  docker compose --profile tools run --rm --entrypoint python ia \
      scripts/ia-upload-paced.py [pause-seconds]
"""

import csv
import os
import sys
import time
from itertools import groupby

from internetarchive import get_item

PAUSE = int(sys.argv[1]) if len(sys.argv) > 1 else 600

with open("scripts/ia-upload.csv", newline="") as f:
    rows = list(csv.DictReader(f))

uploaded = 0
for identifier, group in groupby(rows, key=lambda r: r["identifier"]):
    group = list(group)
    item = get_item(identifier)
    remote = {f["name"]: int(f["size"]) for f in item.files if f.get("source") == "original" and "size" in f}
    todo = [r for r in group if remote.get(r["REMOTE_NAME"]) != os.path.getsize(r["file"])]
    if not todo:
        continue

    if uploaded:
        print(f"waiting {PAUSE}s", flush=True)
        time.sleep(PAUSE)

    # Metadata columns are only filled on an item's first row; subject[n] become a list.
    first = group[0]
    metadata = {k: v for k, v in first.items() if v and k not in ("identifier", "file", "REMOTE_NAME") and not k.startswith("subject[")}
    metadata["subject"] = [v for k, v in first.items() if k.startswith("subject[") and v]

    print(f"{identifier}: uploading {', '.join(r['REMOTE_NAME'] for r in todo)}", flush=True)
    try:
        responses = item.upload(
            {r["REMOTE_NAME"]: r["file"] for r in todo},
            metadata=metadata,
            checksum=True,
            retries=3,
            retries_sleep=60,
        )
    except Exception as e:  # noqa: BLE001 - any failure stops the run
        print(f"{identifier}: STOPPED: {e}", flush=True)
        sys.exit(1)

    failed = [r for r in responses if r is not None and not r.ok]
    if failed:
        print(f"{identifier}: STOPPED: {failed[0].status_code} {failed[0].text[:300]}", flush=True)
        sys.exit(1)

    print(f"{identifier}: done", flush=True)
    uploaded += 1

print(f"finished: {uploaded} items uploaded this run", flush=True)
