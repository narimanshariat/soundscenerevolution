<?php

/*
 * Converts the WordPress database (loaded from the raw dump into the throwaway
 * `db` container) into one Markdown file per published post, and writes
 * scripts/cleanup-report.md listing every change made to the original content.
 *
 *     docker compose --profile raw run --rm importer php scripts/import.php
 *
 * Re-running is safe: source/_episodes/ is regenerated from scratch, except that
 * audio_url values already written back after the archive.org upload are kept.
 */

require __DIR__ . '/../vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;

const ROOT = __DIR__ . '/..';
const EPISODES_DIR = ROOT . '/source/_episodes';
const IMAGES_DIR = ROOT . '/source/assets/images';
const OLD_SITE = 'http://soundscenerevolution.com';

// Posts whose published text was edited by spammers in 2023. Each is restored
// from its last revision saved before 2011, i.e. exactly as Rich wrote it.
const RESTORE_FROM_REVISION = [
    397 => 'Injected sentence promoting an addiction-treatment site (alcoholismtreatment.com)',
    441 => 'Injected clause promoting a WoW-gold seller article on outlookindia.com ("wotlk gold")',
];

// Comments that were approved but are spam or self-promotion.
const DROP_COMMENTS = [
    65 => 'Self-promotion for another podcast',
    197 => 'Generic SEO comment spam ("Came to your blog through AOL...")',
    198 => 'Generic SEO comment spam ("Fantastic tales from the histories...")',
];

// MP3s in uploads that no post references, attached to the episode they belong to.
const ORPHAN_AUDIO = [
    '2009/05/nofriends_phone.mp3' => [
        'post' => 303,
        'title' => 'Bonus: No Friends phone recording',
    ],
];

// Final safety net: any link left in a post that matches one of these is reported.
const SPAM_PATTERN = '~gold|casino|poker|viagra|cialis|pharma|pills|essay|loan|payday|replica|'
    . 'outlook-spotlight|outlookindia|treatment|rehab|seo|crypto|betting~i';

$config = require ROOT . '/config.php';
$authors = $config['authors'];

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);

$probe = json_decode(file_get_contents(__DIR__ . '/audio-probe.json'), true);
$uploads = getenv('RAW_DIR') . '/uploads/wp-content/uploads';
$converter = new HtmlConverter(['header_style' => 'atx', 'strip_tags' => false, 'hard_break' => false]);

$report = [
    'restored' => [],
    'dropped_comments' => [],
    'suspicious_links' => [],
    'notes' => [],
];

$existingAudioUrls = readExistingAudioUrls();
array_map('unlink', glob(EPISODES_DIR . '/*.md'));

$posts = $pdo->query("
    SELECT p.ID, p.guid, p.post_date, p.post_date_gmt, p.post_modified, p.post_name, p.post_title, p.post_content, u.user_login
    FROM wp_posts p JOIN wp_users u ON u.ID = p.post_author
    WHERE p.post_type = 'post' AND p.post_status = 'publish'
    ORDER BY p.post_date
")->fetchAll();

$terms = $pdo->prepare("
    SELECT tt.taxonomy, t.name, t.slug
    FROM wp_term_relationships r
    JOIN wp_term_taxonomy tt USING (term_taxonomy_id)
    JOIN wp_terms t USING (term_id)
    WHERE r.object_id = ? AND tt.taxonomy IN ('category', 'post_tag')
    ORDER BY t.name
");

$media = $pdo->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = '_podPressMedia'");

$revision = $pdo->prepare("
    SELECT post_date, post_content FROM wp_posts
    WHERE post_parent = ? AND post_type = 'revision' AND post_date < '2011-01-01'
    ORDER BY post_date DESC LIMIT 1
");

// Only display name, date and text are selected; commenter emails, IPs, user
// agents and URLs never leave the database.
$comments = $pdo->prepare("
    SELECT comment_ID, comment_author, comment_date, comment_content
    FROM wp_comments
    WHERE comment_post_ID = ? AND comment_approved = '1' AND comment_type IN ('', 'comment')
    ORDER BY comment_date
");

foreach ($posts as $post) {
    $id = (int) $post['ID'];
    $content = $post['post_content'];

    if (isset(RESTORE_FROM_REVISION[$id])) {
        $revision->execute([$id]);
        $original = $revision->fetch() ?: throw new RuntimeException("No pre-2011 revision for post $id");
        $report['restored'][] = [
            'title' => $post['post_title'],
            'reason' => RESTORE_FROM_REVISION[$id],
            'edited' => $post['post_modified'],
            'restored_from' => $original['post_date'],
            'removed_links' => array_values(array_diff(hrefs($content), hrefs($original['post_content']))),
        ];
        $content = $original['post_content'];
    }

    foreach (hrefs($content) as $href) {
        if (preg_match(SPAM_PATTERN, parse_url($href, PHP_URL_HOST) . parse_url($href, PHP_URL_PATH))) {
            $report['suspicious_links'][] = "{$post['post_title']}: $href";
        }
    }

    $terms->execute([$id]);
    $categories = $tags = [];
    foreach ($terms->fetchAll() as $term) {
        if ($term['taxonomy'] === 'category') {
            $categories[$term['slug']] = $term['name'];
        } else {
            $tags[$term['slug']] = $term['name'];
        }
    }

    $audio = audioFor($id, $media, $probe, $uploads);

    $comments->execute([$id]);
    $kept = [];
    foreach ($comments->fetchAll() as $comment) {
        if (isset(DROP_COMMENTS[$comment['comment_ID']])) {
            $report['dropped_comments'][] = [
                'post' => $post['post_title'],
                'date' => $comment['comment_date'],
                'reason' => DROP_COMMENTS[$comment['comment_ID']],
            ];
            continue;
        }
        $kept[] = [
            'name' => decode($comment['comment_author']),
            'date' => $comment['comment_date'],
            'text' => trim(strip_tags(decode(str_replace("\r\n", "\n", $comment['comment_content'])))),
        ];
    }

    $episode = preg_match('/- Podcast (\d+)$/', trim($post['post_title']), $m) ? (int) $m[1] : null;

    $front = [
        'title' => decode($post['post_title']),
        'slug' => $post['post_name'],
        'wp_id' => $id,
        // Podcast apps recognise episodes they already have by this; never change it.
        'guid' => $post['guid'],
        'author' => $authors[$post['user_login']] ?? throw new RuntimeException("Unknown author {$post['user_login']}"),
        'author_slug' => $post['user_login'],
        'episode' => $episode,
        'categories' => $categories,
        'tags' => $tags,
    ];

    if ($audio) {
        $main = array_shift($audio);
        $front += [
            'audio_file' => $main['file'],
            'audio_title' => $main['title'],
            'audio_original_url' => $main['original_url'],
            'audio_size' => $main['size'],
            'audio_duration' => $main['duration'],
            'audio_url' => $existingAudioUrls[$id][$main['file']] ?? null,
        ];
        if ($audio) {
            $front['extra_audio'] = array_map(fn ($a) => $a + ['url' => $existingAudioUrls[$id][$a['file']] ?? null], $audio);
        }
    }

    if ($kept) {
        $front['comments'] = $kept;
    }

    // Dates are written by hand so YAML reads them as timestamps. `date` is the
    // old site's local time (Pacific, UTC-7) and drives the permalink; `date_gmt`
    // is the same moment in UTC, for the feed.
    $yaml = "---\n"
        . "date: {$post['post_date']}\n"
        . "date_gmt: {$post['post_date_gmt']}\n"
        // A trailing literal block (a multi-line comment) is dumped without a final newline.
        . rtrim(Yaml::dump($front, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_TILDE)) . "\n"
        . "---\n";

    $file = sprintf('%s/%s-%s.md', EPISODES_DIR, substr($post['post_date'], 0, 10), $post['post_name']);
    file_put_contents($file, $yaml . "\n" . toMarkdown($content, $converter) . "\n");
}

$images = copyImages($uploads);
writeReport($report, $pdo, count($posts), $images);

printf("Imported %d posts, %d images. Report: scripts/cleanup-report.md\n", count($posts), $images);

/** @return list<array{file: string, title: string, original_url: string, size: int, duration: int}> */
function audioFor(int $id, PDOStatement $media, array $probe, string $uploads): array
{
    $media->execute([$id]);
    $value = $media->fetchColumn();
    $files = [];

    foreach ($value ? unserialize($value, ['allowed_classes' => false]) : [] as $item) {
        $files[] = audioEntry('audio/' . $item['URI'], decode($item['title']), $item['duration'] ?? '', $probe, $uploads);
    }

    foreach (ORPHAN_AUDIO as $path => $orphan) {
        if ($orphan['post'] === $id) {
            $files[] = audioEntry($path, $orphan['title'], '', $probe, $uploads);
        }
    }

    return $files;
}

function audioEntry(string $path, string $title, string $duration, array $probe, string $uploads): array
{
    if (!is_file("$uploads/$path")) {
        throw new RuntimeException("Missing audio file $path");
    }

    // podPress stored durations as M:SS or H:MM:SS; fall back to the probe when missing.
    $seconds = $duration !== ''
        ? array_reduce(explode(':', $duration), fn ($total, $part) => $total * 60 + (int) $part, 0)
        : $probe[$path]['duration'];

    return [
        'file' => basename($path),
        'title' => $title,
        'original_url' => OLD_SITE . '/wp-content/uploads/' . $path,
        'size' => filesize("$uploads/$path"),
        'duration' => $seconds,
    ];
}

/**
 * WordPress stored post bodies without <p> tags and added them at display time
 * (wpautop). Do the same here: blank lines separate paragraphs and single
 * newlines inside a paragraph are line breaks.
 */
function toMarkdown(string $html, HtmlConverter $converter): string
{
    $block = 'ul|ol|blockquote|h[1-6]|table|pre|hr';
    $html = str_replace("\r\n", "\n", trim($html));
    // A few posts were pasted from Gmail inside bare <div>s. Treat them as the
    // paragraph breaks they displayed as, so their contents convert normally.
    $html = preg_replace('~</?div\b[^>]*>~i', "\n\n", $html);
    // Browsers ignored stray spaces inside href values; the converter would encode them.
    $html = preg_replace('~href="\s*([^"]*?)\s*"~i', 'href="$1"', $html);
    // Give block-level elements their own paragraphs, as wpautop did.
    $html = preg_replace("~\n?(<(?:$block)\b)~i", "\n\n$1", $html);
    $html = preg_replace("~(</(?:$block)>)\n?~i", "$1\n\n", $html);

    $paragraphs = [];
    foreach (preg_split('/\n\s*\n/', $html) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }
        $paragraphs[] = preg_match("~^<(?:$block)\b~i", $chunk)
            ? $chunk
            : '<p>' . str_replace("\n", "<br>\n", $chunk) . '</p>';
    }

    $markdown = $converter->convert(implode("\n\n", $paragraphs));

    // The converter marks <br> with two trailing spaces, which editors strip.
    // A trailing backslash is the CommonMark hard break that survives.
    return preg_replace('/ {2}$/m', '\\', $markdown);
}

function hrefs(string $html): array
{
    preg_match_all('~href=["\']?\s*([^"\'\s>]+)~i', $html, $m);

    return array_unique($m[1]);
}

function decode(string $text): string
{
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Keeps archive.org URLs that were written back into front matter by an earlier run. */
function readExistingAudioUrls(): array
{
    $urls = [];
    foreach (glob(EPISODES_DIR . '/*.md') as $file) {
        if (!preg_match('/^---\n(.*?)\n---/s', file_get_contents($file), $m)) {
            continue;
        }
        $front = Yaml::parse($m[1]);
        if (!empty($front['audio_url'])) {
            $urls[$front['wp_id']][$front['audio_file']] = $front['audio_url'];
        }
        foreach ($front['extra_audio'] ?? [] as $extra) {
            if (!empty($extra['url'])) {
                $urls[$front['wp_id']][$extra['file']] = $extra['url'];
            }
        }
    }

    return $urls;
}

/**
 * Copies images from the dated upload folders, keeping their wp-content paths.
 * Only files that really are images are copied; anything else in uploads is ignored.
 */
function copyImages(string $uploads): int
{
    $count = 0;
    foreach (glob("$uploads/[12][0-9][0-9][0-9]/[01][0-9]/*") as $path) {
        $ok = preg_match('/\.(jpe?g|png|gif)$/i', $path) && @getimagesize($path) !== false;
        if (!$ok) {
            continue;
        }
        $target = IMAGES_DIR . '/wp-content/uploads/' . substr($path, strlen($uploads) + 1);
        @mkdir(dirname($target), 0755, true);
        copy($path, $target);
        $count++;
    }

    return $count;
}

function writeReport(array $report, PDO $pdo, int $postCount, int $imageCount): void
{
    $excluded = $pdo->query("
        SELECT CASE WHEN comment_type = 'pingback' THEN 'pingback'
                    WHEN comment_approved = '1' THEN 'approved'
                    WHEN comment_approved = '0' THEN 'pending (never approved)'
                    ELSE comment_approved END AS status,
               COUNT(*) AS n
        FROM wp_comments GROUP BY 1 ORDER BY 1
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $unusedTags = $pdo->query("
        SELECT t.name FROM wp_terms t JOIN wp_term_taxonomy tt USING (term_id)
        WHERE tt.taxonomy = 'post_tag' AND tt.count = 0 ORDER BY t.name
    ")->fetchAll(PDO::FETCH_COLUMN);

    $out = "# Cleanup report\n\n"
        . "Generated by `scripts/import.php`. Every change made to the original WordPress content is listed here.\n"
        . "Imported $postCount published posts and $imageCount images.\n\n"
        . "## Posts restored to their pre-spam text\n\n"
        . "These posts were edited in 2023 to insert spam. The published text was replaced with the post's last revision saved before 2011.\n";

    foreach ($report['restored'] as $r) {
        $out .= "\n### {$r['title']}\n\n"
            . "- Injection: {$r['reason']}\n"
            . "- Spam edit dated: {$r['edited']}\n"
            . "- Restored from revision saved: {$r['restored_from']}\n";
        foreach ($r['removed_links'] as $link) {
            $out .= "- Removed link: `$link`\n";
        }
    }

    $out .= "\n## Comments dropped\n\n";
    foreach ($report['dropped_comments'] as $c) {
        $out .= "- {$c['date']} on \"{$c['post']}\": {$c['reason']}\n";
    }
    $out .= "\nNot imported at all, by status in the database:\n\n";
    foreach ($excluded as $status => $n) {
        if ($status !== 'approved') {
            $out .= "- $status: $n\n";
        }
    }

    $out .= "\n## Remaining suspicious links\n\n"
        . ($report['suspicious_links']
            ? implode("\n", array_map(fn ($l) => "- $l", $report['suspicious_links'])) . "\n"
            : "None. Every link left in the imported posts was checked against the spam pattern in `scripts/import.php`.\n");

    $out .= "\n## Other notes\n\n";
    foreach (ORPHAN_AUDIO as $path => $orphan) {
        $out .= "- `$path` was not referenced by any post; attached to post {$orphan['post']} as \"{$orphan['title']}\". The title is a guess.\n";
    }
    if ($unusedTags) {
        $out .= '- Unused tags not imported: ' . implode(', ', $unusedTags) . "\n";
    }

    file_put_contents(__DIR__ . '/cleanup-report.md', $out);
}
