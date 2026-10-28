<?php

/**
 * After the archive.org upload: checks every audio file is on its item at the
 * right size, then writes its download URL into the episode's front matter
 * (audio_url, and url on each extra_audio entry). Files that are missing or
 * the wrong size are listed and left as `~`. Run in the php container:
 *
 *   docker compose run --rm php php scripts/write-audio-urls.php
 *
 * Only those lines change, so the rest of each file stays byte-for-byte as the
 * importer wrote it, and the importer keeps these URLs on later runs.
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$problems = [];
$written = 0;

foreach (glob(__DIR__ . '/../source/_episodes/*.md') as $path) {
    $text = file_get_contents($path);
    preg_match('/^---\n(.*?)\n---/s', $text, $m);
    $post = Yaml::parse($m[1]);

    if (empty($post['audio_file'])) {
        continue;
    }

    $identifier = $post['episode']
        ? sprintf('ssr-podcast-%03d', $post['episode'])
        : 'ssr-' . $post['slug'];

    $remote = remoteSizes($identifier);
    $expected = [$post['audio_file'] => $post['audio_size']];
    foreach ($post['extra_audio'] ?? [] as $extra) {
        $expected[$extra['file']] = $extra['size'];
    }

    $urls = [];
    foreach ($expected as $file => $size) {
        if (! isset($remote[$file])) {
            $problems[] = "$identifier/$file: not on archive.org";
        } elseif ($remote[$file] !== (int) $size) {
            $problems[] = "$identifier/$file: size {$remote[$file]} on archive.org, $size locally";
        } else {
            $urls[$file] = "https://archive.org/download/$identifier/" . rawurlencode($file);
        }
    }

    // Line edits: `audio_url: ~` for the main file, `url: ~` after each extra's `file:` line.
    $lines = explode("\n", $text);
    $extraFile = null;
    foreach ($lines as $i => $line) {
        if ($line === '---' && $i > 0) {
            break;
        }
        if (preg_match('/^    file: (.+)$/', $line, $f)) {
            $extraFile = Yaml::parse($f[1]);
        } elseif ($line === 'audio_url: ~' && isset($urls[$post['audio_file']])) {
            $lines[$i] = "audio_url: '{$urls[$post['audio_file']]}'";
        } elseif ($line === '    url: ~' && $extraFile !== null && isset($urls[$extraFile])) {
            $lines[$i] = "    url: '{$urls[$extraFile]}'";
        }
    }

    $updated = implode("\n", $lines);
    if ($updated !== $text) {
        file_put_contents($path, $updated);
        $written++;
    }
}

echo "$written episode files updated\n";

if ($problems) {
    fwrite(STDERR, count($problems) . " problem(s):\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}

/** File name → size for the original files on an archive.org item. */
function remoteSizes(string $identifier): array
{
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $json = @file_get_contents("https://archive.org/metadata/$identifier");
        if ($json !== false) {
            break;
        }
        sleep(5 * $attempt);
    }

    $sizes = [];
    foreach (json_decode($json ?: '{}', true)['files'] ?? [] as $file) {
        if (($file['source'] ?? '') === 'original' && isset($file['size'])) {
            $sizes[$file['name']] = (int) $file['size'];
        }
    }

    return $sizes;
}
