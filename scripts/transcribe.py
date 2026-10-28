"""Transcribe episode MP3s into WebVTT and plain text.

Output is the raw transcript, song lyrics included. It goes to the git-ignored
transcripts-raw/ folder and is filtered before anything is published.

Resumable: an MP3 whose .vtt already exists is skipped, so the job can be
stopped and restarted at any point.

    docker compose run --rm whisper /raw/uploads/wp-content/uploads/audio transcripts-raw [file ...]
"""
import os
import sys
import time

from faster_whisper import WhisperModel

MODEL = os.environ.get("WHISPER_MODEL", "large-v3-turbo")


def stamp(seconds):
    h, rem = divmod(seconds, 3600)
    m, s = divmod(rem, 60)
    return f"{int(h):02d}:{int(m):02d}:{s:06.3f}"


def transcribe(model, src, out_dir):
    name = os.path.splitext(os.path.basename(src))[0]
    vtt_path = os.path.join(out_dir, name + ".vtt")
    if os.path.exists(vtt_path):
        print(f"skip {name} (already done)", flush=True)
        return

    started = time.time()
    segments, info = model.transcribe(src, language="en", vad_filter=True, beam_size=5)
    cues, lines = [], []
    for seg in segments:
        text = seg.text.strip()
        if not text:
            continue
        cues.append(f"{stamp(seg.start)} --> {stamp(seg.end)}\n{text}\n")
        lines.append(text)

    # Write to temp files first so an interrupted run never leaves a partial .vtt behind.
    with open(vtt_path + ".tmp", "w", encoding="utf-8") as f:
        f.write("WEBVTT\n\n" + "\n".join(cues))
    with open(os.path.join(out_dir, name + ".txt"), "w", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")
    os.replace(vtt_path + ".tmp", vtt_path)

    took = time.time() - started
    print(f"done {name}: {info.duration / 60:.1f} min audio in {took / 60:.1f} min "
          f"({info.duration / took:.2f}x realtime)", flush=True)


def main():
    src_dir, out_dir, *only = sys.argv[1:]
    os.makedirs(out_dir, exist_ok=True)
    files = only or sorted(f for f in os.listdir(src_dir) if f.lower().endswith(".mp3"))
    model = WhisperModel(MODEL, device="cpu", compute_type="int8", cpu_threads=os.cpu_count())
    for f in files:
        transcribe(model, os.path.join(src_dir, f), out_dir)


if __name__ == "__main__":
    main()
