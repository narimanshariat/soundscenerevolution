"""Publishes transcripts with the songs taken out.

Reads the raw Whisper output (transcripts-raw/<name>.vtt, lyrics included) and
the speech/music segments from find-music.py (<name>.music.json), drops every
line that falls inside a song, and writes transcripts/<name>.txt: one paragraph
per line, with [song] or [music] where something was cut. The raw files are
never changed.

A song is a block of continuous sound (no pause of SILENCE seconds or more)
lasting at least MIN_BLOCK seconds that the segmenter heard at least MIN_MUSIC
seconds of music in. The continuity test matters because the segmenter labels
much punk singing as "speech", while real conversation pauses every few
seconds. Blocks closer together than MERGE_GAP seconds are joined.

Inside a block, runs of dense, back-to-back lines are kept: that is the host
talking over a music bed, not singing (see talk_runs). Lines Whisper repeated
in a loop are dropped everywhere, and so are the lines listed in
scripts/transcript-cuts.json: lyrics found by reviewing the report. --report
lists every line inside a block, "+" kept and "-" dropped.

    docker compose run --rm --entrypoint python music scripts/filter-transcripts.py [--report] [name ...]
"""
import glob
import json
import os
import re
import sys

RAW = "transcripts-raw"
OUT = "transcripts"
CUTS = "scripts/transcript-cuts.json"
SILENCE = 0.3
MIN_BLOCK = 30
MIN_MUSIC = 8
MERGE_GAP = 10
DROP_OVERLAP = 0.5
TALK_WORDS_PER_SEC = 1.6
TALK_MAX_CUE = 12
TALK_MAX_GAP = 2.5
TALK_MIN_RUN = 3
TALK_EDGE = 15
TALK_JOIN_GAP = 3
REPEAT_RUN = 3
PARAGRAPH_GAP = 2.0
PARAGRAPH_CHARS = 700


def read_cues(path):
    pattern = r"(\d+):(\d+):([\d.]+) --> (\d+):(\d+):([\d.]+)\n(.+)"
    cues = []
    for m in re.finditer(pattern, open(path, encoding="utf-8").read()):
        g = m.groups()
        start = int(g[0]) * 3600 + int(g[1]) * 60 + float(g[2])
        end = int(g[3]) * 3600 + int(g[4]) * 60 + float(g[5])
        cues.append((start, end, g[6].strip()))
    return cues


def song_blocks(segments):
    """Continuous stretches of sound that contain enough music to be a song."""
    stretches, current = [], None
    for label, start, end in segments:
        if label == "noEnergy" and end - start >= SILENCE:
            current = None
            continue
        if current is None:
            current = [start, end, 0.0]
            stretches.append(current)
        current[1] = end
        if label == "music":
            current[2] += end - start

    blocks = [[s, e] for s, e, music in stretches if e - s >= MIN_BLOCK and music >= MIN_MUSIC]
    # Short music runs on their own (jingles, song tails) count too.
    blocks += [[s, e] for label, s, e in segments if label == "music" and e - s >= MIN_MUSIC]
    blocks.sort()

    merged = []
    for s, e in blocks:
        if merged and s - merged[-1][1] < MERGE_GAP:
            merged[-1][1] = max(merged[-1][1], e)
        else:
            merged.append([s, e])
    return merged


def overlap(start, end, blocks):
    covered = sum(max(0.0, min(end, b) - max(start, a)) for a, b in blocks)
    return covered / max(end - start, 0.01)


def is_dense(cue):
    start, end, text = cue
    return end - start <= TALK_MAX_CUE and len(text.split()) / max(end - start, 0.1) >= TALK_WORDS_PER_SEC


def talk_runs(cues, blocks):
    """Indexes of cues in runs of TALK_MIN_RUN or more dense, back-to-back lines
    that start or end within TALK_EDGE seconds of a song block's edge.

    These are someone talking over a music bed (introducing a song, or coming
    back out of one), which Whisper transcribes as a steady run of short lines.
    Sung lyrics mostly come out sparse, long or garbled; a cleanly sung verse
    can look like talk, but it sits in the middle of a block, or at its edge
    without joining up with the conversation around it.
    """
    runs, run = [], []
    for i, cue in enumerate(cues):
        if is_dense(cue) and (not run or cue[0] - cues[run[-1]][1] <= TALK_MAX_GAP):
            run.append(i)
            continue
        runs.append(run)
        run = [i] if is_dense(cue) else []
    runs.append(run)

    def outside(i):
        return 0 <= i < len(cues) and overlap(cues[i][0], cues[i][1], blocks) < DROP_OVERLAP

    keep = set()
    for run in runs:
        if len(run) < TALK_MIN_RUN:
            continue
        start, end = cues[run[0]][0], cues[run[-1]][1]
        inside = [(a, b) for a, b in blocks if start < b and end > a]
        at_edge = all(start <= a + TALK_EDGE or end >= b - TALK_EDGE for a, b in inside)
        # It must also carry on from, or into, ordinary speech outside the song
        # (or open the show), not stand alone the way a song's first verse does.
        before, after = run[0] - 1, run[-1] + 1
        joined = (start < TALK_EDGE
                  or outside(before) and start - cues[before][1] <= TALK_JOIN_GAP
                  or outside(after) and cues[after][0] - end <= TALK_JOIN_GAP)
        if at_edge and joined:
            keep.update(run)
    return keep


def repeats(cues):
    """Indexes of lines Whisper repeated REPEAT_RUN or more times in a row (a hallucination loop)."""
    loop, i = set(), 0
    while i < len(cues):
        j = i
        while j + 1 < len(cues) and cues[j + 1][2] == cues[i][2]:
            j += 1
        if j - i + 1 >= REPEAT_RUN:
            loop.update(range(i, j + 1))
        i = j + 1
    return loop


def filter_episode(name, report, cuts):
    cues = read_cues(os.path.join(RAW, name + ".vtt"))
    blocks = song_blocks(json.load(open(os.path.join(RAW, name + ".music.json"))))
    talk, loop = talk_runs(cues, blocks), repeats(cues)
    # Reviewed by hand: lyrics the rules above let through.
    loop |= {n for n, (start, _, _) in enumerate(cues) if any(a <= start <= b for a, b, _ in cuts.get(name, []))}

    paragraphs, current, last_end, dropped = [], [], None, []
    marked = set()

    def flush():
        if current:
            paragraphs.append(" ".join(current))
            current.clear()

    def mark(i):
        if i not in marked:
            marked.add(i)
            flush()
            a, b = blocks[i]
            paragraphs.append("[song]" if b - a >= 60 else "[music]")

    for n, (start, end, text) in enumerate(cues):
        in_song = overlap(start, end, blocks) >= DROP_OVERLAP
        if n in loop or (in_song and n not in talk):
            dropped.append((start, end, text))
            for i, (a, b) in enumerate(blocks):
                if a - 1 <= start <= b:
                    mark(i)
            continue
        # Blocks Whisper wrote nothing for still get a marker.
        for i, (a, b) in enumerate(blocks):
            if b <= start + 1 and (last_end is None or a >= last_end - 1):
                mark(i)
        if current and (start - last_end >= PARAGRAPH_GAP
                        or sum(map(len, current)) >= PARAGRAPH_CHARS and current[-1][-1:] in ".?!"):
            flush()
        current.append(text)
        last_end = end
    flush()
    for i, (a, b) in enumerate(blocks):
        if last_end is None or a >= last_end - 1:
            mark(i)

    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, name + ".txt"), "w", encoding="utf-8") as f:
        f.write("\n".join(paragraphs) + "\n")

    song_minutes = sum(b - a for a, b in blocks) / 60
    print(f"{name}: {len(blocks)} songs/music ({song_minutes:.1f} min), "
          f"{len(dropped)} of {len(cues)} lines dropped", flush=True)
    if report:
        for a, b in blocks:
            print(f"    block {a / 60:6.2f}-{b / 60:6.2f}m ({b - a:5.0f}s)")
        for n, (start, end, text) in enumerate(cues):
            if overlap(start, end, blocks) >= DROP_OVERLAP or n in loop:
                verdict = "-" if (start, end, text) in dropped else "+"
                print(f"    {verdict} {start / 60:6.2f}m {text[:90]}")


def main():
    args = sys.argv[1:]
    report = "--report" in args
    names = [a for a in args if a != "--report"] or sorted(
        os.path.basename(p)[: -len(".music.json")] for p in glob.glob(f"{RAW}/*.music.json"))
    cuts = json.load(open(CUTS))
    for name in names:
        filter_episode(name, report, cuts)


if __name__ == "__main__":
    main()
