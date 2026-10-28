<?php

/**
 * Builds scripts/ia-upload.csv for `ia upload --spreadsheet`: one row per audio
 * file, with item metadata on each item's first row. File paths point at the
 * raw uploads as the ia container sees them (/raw). Run in the php container:
 *
 *   docker compose run --rm php php scripts/build-ia-csv.php [identifier ...]
 *
 * With identifiers, only those items are written (for the test upload).
 */

require __DIR__ . '/../vendor/autoload.php';

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

const SITE = 'https://soundscenerevolution.com';
const UPLOADS = '/raw/uploads/wp-content/uploads/';
const LOCAL_UPLOADS = __DIR__ . '/../ssr-raw/uploads/wp-content/uploads/';
const DESCRIPTION_LENGTH = 600;
const NOTES = 'Sound Scene Revolution was a podcast of news and interviews with local bands, 2005–2010. '
    . 'The music played in this episode belongs to the bands who made it; it is included here as part of the original broadcast.';

$only = array_slice($argv, 1);
$markdown = new CommonMarkConverter();
$items = [];

foreach (glob(__DIR__ . '/../source/_episodes/*.md') as $path) {
    [, $frontMatter, $body] = preg_split('/^---$/m', file_get_contents($path), 3);
    $post = Yaml::parse($frontMatter);

    if (empty($post['audio_file'])) {
        continue;
    }

    $identifier = $post['episode']
        ? sprintf('ssr-podcast-%03d', $post['episode'])
        : 'ssr-' . $post['slug'];

    if ($only && ! in_array($identifier, $only, true)) {
        continue;
    }

    $date = $post['date'] instanceof DateTimeInterface ? $post['date'] : new DateTime('@' . $post['date']);
    $permalink = SITE . '/' . $date->format('Y/m/d') . '/' . $post['slug'] . '/';

    $files = [['file' => $post['audio_file'], 'original_url' => $post['audio_original_url']]];
    foreach ($post['extra_audio'] ?? [] as $extra) {
        $files[] = $extra;
    }

    $subjects = array_values(array_unique([
        ...array_values($post['categories'] ?? []),
        ...array_values($post['tags'] ?? []),
        'soundscenerevolution',
    ]));

    $items[$identifier] = [
        'metadata' => [
            'mediatype' => 'audio',
            'collection' => 'opensource_audio',
            'title' => $post['title'],
            'creator' => 'Sound Scene Revolution',
            'date' => $date->format('Y-m-d'),
            'description' => summary($markdown->convert($body)->getContent())
                . "\n\nOriginally posted on Sound Scene Revolution: " . $permalink,
            'notes' => NOTES,
        ],
        'subjects' => $subjects,
        'files' => array_map(function ($file) {
            $relative = substr($file['original_url'], strpos($file['original_url'], '/wp-content/uploads/') + strlen('/wp-content/uploads/'));
            if (! is_file(LOCAL_UPLOADS . $relative)) {
                fwrite(STDERR, "missing audio file: $relative\n");
                exit(1);
            }

            return ['local' => UPLOADS . $relative, 'remote' => $file['file']];
        }, $files),
    ];
}

ksort($items);

$maxSubjects = max(array_map(fn ($item) => count($item['subjects']), $items));
$subjectColumns = array_map(fn ($i) => "subject[$i]", range(0, $maxSubjects - 1));
$metadataColumns = array_keys(reset($items)['metadata']);

$out = fopen(__DIR__ . '/ia-upload.csv', 'w');
fputcsv($out, ['identifier', 'file', 'REMOTE_NAME', ...$metadataColumns, ...$subjectColumns], escape: '');

$fileCount = 0;
foreach ($items as $identifier => $item) {
    foreach ($item['files'] as $i => $file) {
        $row = [$identifier, $file['local'], $file['remote']];
        // Metadata only on the first row of an item; ia skips empty cells.
        $row = $i === 0
            ? [...$row, ...array_values($item['metadata']), ...array_pad($item['subjects'], $maxSubjects, '')]
            : [...$row, ...array_fill(0, count($metadataColumns) + $maxSubjects, '')];
        fputcsv($out, $row, escape: '');
        $fileCount++;
    }
}

fclose($out);
echo count($items) . " items, $fileCount files → scripts/ia-upload.csv\n";

/** Plain-text summary of the post body, cut at a word boundary. */
function summary(string $html): string
{
    $text = html_entity_decode(strip_tags(preg_replace('~</(p|li|h\d|blockquote)>~i', "$0\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace(['/[ \t\x{00A0}]+/u', '/\s*\n\s*/u'], [' ', ' '], $text));

    if (mb_strlen($text) <= DESCRIPTION_LENGTH) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, DESCRIPTION_LENGTH), ' ')), ' ,.;:') . '…';
}
