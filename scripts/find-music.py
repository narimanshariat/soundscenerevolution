"""Finds the music in each transcribed episode, for the transcript filter.

For every transcripts-raw/<name>.vtt that has no <name>.music.json yet, runs
inaSpeechSegmenter over the matching MP3 and writes the speech/music/noise
segments (label, start, end in seconds) next to it. Resumable; the output stays
in the git-ignored transcripts-raw/ folder with the raw transcripts.

    docker compose run --rm music [name ...]
"""
import glob
import json
import os
import sys
import subprocess
import time

from inaSpeechSegmenter import Segmenter

RAW = "transcripts-raw"
AUDIO_DIRS = ["/raw/uploads/wp-content/uploads/audio", "/raw/uploads/wp-content/uploads/2009/05"]
# Long episodes are segmented in pieces; a whole 85-minute file runs out of memory.
CHUNK = 600


def find_mp3(name):
    for d in AUDIO_DIRS:
        path = os.path.join(d, name + ".mp3")
        if os.path.exists(path):
            return path
    return None


def duration(path):
    out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", path],
                         capture_output=True, text=True, check=True)
    return float(out.stdout)


def main():
    names = sys.argv[1:] or sorted(os.path.basename(p)[:-4] for p in glob.glob(f"{RAW}/*.vtt"))
    segmenter = None
    for name in names:
        out = os.path.join(RAW, name + ".music.json")
        if os.path.exists(out):
            continue
        mp3 = find_mp3(name)
        if not mp3:
            print(f"no audio for {name}", flush=True)
            continue

        segmenter = segmenter or Segmenter(vad_engine="smn", detect_gender=False)
        started = time.time()
        segments = []
        for offset in range(0, int(duration(mp3)) + 1, CHUNK):
            for label, start, end in segmenter(mp3, start_sec=offset, stop_sec=offset + CHUNK):
                segments.append([label, round(start, 2), round(end, 2)])  # already file-relative
        with open(out + ".tmp", "w") as f:
            json.dump(segments, f)
        os.replace(out + ".tmp", out)

        music = sum(end - start for label, start, end in segments if label == "music")
        print(f"done {name}: {music / 60:.1f} min music, {time.time() - started:.0f}s", flush=True)


if __name__ == "__main__":
    main()
