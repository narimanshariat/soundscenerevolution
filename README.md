# Sound Scene Revolution

The archive of **Sound Scene Revolution**, a weekly podcast of news and interviews with punk, hardcore and indie bands, 2005–2010. It has 121 episodes plus a few extras.

**[soundscenerevolution.com](https://soundscenerevolution.com)**

The original WordPress site has been rebuilt as a static site. Every post keeps its original address, text and comments, and old links (posts, categories, tags, monthly archives, MP3 links and the feed) still work or redirect.

The music played in these episodes belongs to the bands who made it.

## How it works

- **Site:** [Jigsaw](https://jigsaw.tighten.com/) (PHP, Blade templates), one Markdown file per post in `source/_episodes/`. GitHub Actions builds it and GitHub Pages serves it (`.github/workflows/deploy.yml`) on every push to `main`.
- **Audio:** served from Cloudflare R2 (set by `audioHost` in `config.php`). Every episode is also preserved on the [Internet Archive](https://archive.org/search?query=subject%3Asoundscenerevolution) as `ssr-podcast-NNN`. No audio is kept in this repo.
- **Feed:** `/feed.xml`, and `/feed/` for old subscribers, with enclosures, iTunes tags and the original GUIDs.
- **Transcripts:** generated with Whisper, with the songs removed (see below). Shown on each episode page, labelled as auto-generated.

## Running it locally

Everything runs in Docker; nothing needs installing besides Docker itself.

```sh
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/jigsaw build
docker compose run --rm --service-ports php php -S 0.0.0.0:8000 -t build_local scripts/dev-router.php
```

Then open http://localhost:8000. The router mimics GitHub Pages, including the 404 page that handles old-URL redirects.

## Layout

| Path | What it is |
|---|---|
| `source/_episodes/` | The posts: front matter (date, tags, audio, comments) and the original text |
| `source/_layouts/`, `source/_partials/` | Templates |
| `source/assets/` | CSS and images |
| `transcripts/` | Published transcripts, songs removed |
| `app/Archive.php` | Category, tag and month listings |
| `config.php`, `bootstrap.php` | Site config, helpers and post-build steps |
| `scripts/` | One-off import, upload and transcript tools (below) |
| `compose.yaml`, `docker/` | The Docker tooling |

## How the archive was made

These scripts are kept so the work can be checked or repeated. The raw inputs (database dump and uploads) aren't in the repo; they go in the git-ignored `ssr-raw/` folder.

1. **Import:** `scripts/import.php` reads the WordPress database dump into `source/_episodes/`. It keeps the original text as written. Spam that was injected into posts is the only thing removed, and every removal is listed in [`scripts/cleanup-report.md`](scripts/cleanup-report.md). Comments are imported with display name, date and text only.
2. **Audio:** `scripts/build-ia-csv.php` writes the Internet Archive metadata (`scripts/ia-upload.csv`), `scripts/ia-upload-paced.py` uploads it, and `scripts/write-audio-urls.php` records each verified archive.org copy in the front matter.
3. **Transcripts:**
   - `scripts/transcribe.py` runs Whisper. Its raw output, lyrics included, stays in the git-ignored `transcripts-raw/`.
   - `scripts/find-music.py` finds the songs with [inaSpeechSegmenter](https://github.com/ina-foss/inaSpeechSegmenter).
   - `scripts/filter-transcripts.py` drops the lines sung during songs and keeps the hosts talking over music beds.
   - The few lyrics it misses are listed by hand in `scripts/transcript-cuts.json`.

See the comment at the top of `compose.yaml` for the commands.

## Rules for changes

[`CLAUDE.md`](CLAUDE.md) has the rules for this repo. In short:
- URLs never change.
- No audio, raw data or credentials in git.
- No commenter personal data.
- Post text stays as originally written.

## Credits

Sound Scene Revolution was made by Rich and Sound Guy Nariman, with thanks to every band who sat down to talk with us.
