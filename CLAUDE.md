# Sound Scene Revolution archive

Static archive of the Sound Scene Revolution podcast (2005–2010), built with Jigsaw and served by GitHub Pages. Audio lives on the Internet Archive. This repo is public.

## Permanent rules

- **URLs never change.** Posts `/YYYY/MM/DD/slug/`, categories `/category/<slug>/`, tags `/tag/<slug>/`, month archives `/YYYY/MM/`, homepage pagination `/page/N/`. Inbound links from 2005 onward depend on these.
- **Internet Archive identifiers:** `ssr-podcast-NNN` (zero-padded, e.g. `ssr-podcast-007`) for numbered episodes; `ssr-<slug>` for other posts with audio. Never rename an existing item.
- **No audio or raw data in git.** No `*.mp3`/`*.m4a`/`*.wav`, no SQL dumps, no WordPress exports, no `wp-config.php`. Raw inputs live in the git-ignored `ssr-raw/` folder.
- **No commenter personal data.** Only commenter display name, date and text are ever imported. Never emails, IPs, user agents or URLs.
- **Original post text stays as written** — typos, swearing and all. The only permitted changes are spam removal, logged in `scripts/cleanup-report.md`. Old dead links (MySpace, Posterous, etc.) stay; they're history.
- **No credentials in the repo.** archive.org credentials live only in the `ia-config` Docker volume.
- Run `docker compose run --rm gitleaks` and review `git status` before every commit and push.

## Tooling

Everything runs in Docker; don't install tools on the host. See the comment at the top of `compose.yaml` for the commands.
