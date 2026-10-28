<?php

use Illuminate\Support\Str;
use Ssr\Archive;

return [
    'production' => false,
    'baseUrl' => 'http://localhost:8000',
    'title' => 'Sound Scene Revolution',
    'tagline' => 'News and interviews with local bands, 2005–2010',
    'description' => 'An archive of the Sound Scene Revolution podcast (2005–2010): interviews with punk, hardcore and indie bands.',
    'perPage' => 10,

    // CommonMark with no extensions: in particular no SmartPunct, so posts keep
    // their original straight quotes and dashes.
    'commonmark' => ['extensions' => []],

    'collections' => [
        // Every post from the old blog, episode or not. The URL scheme must never change.
        'episodes' => [
            'path' => fn ($page) => date('Y/m/d', $page->date) . '/' . $page->slug,
            'sort' => '-date',
            'extends' => '_layouts.episode',
        ],
        // Listing pages generated from the episodes' front matter (see app/Archive.php).
        'categories' => [
            'path' => 'category/{filename}',
            'extends' => '_layouts.listing',
            'items' => fn () => Archive::listingPages('category'),
        ],
        'tags' => [
            'path' => 'tag/{filename}',
            'extends' => '_layouts.listing',
            'items' => fn () => Archive::listingPages('tag'),
        ],
        'months' => [
            'path' => fn ($page) => str_replace('-', '/', $page->key),
            'extends' => '_layouts.listing',
            'items' => fn () => Archive::listingPages('month'),
        ],
        'years' => [
            'path' => '{filename}',
            'extends' => '_layouts.listing',
            'items' => fn () => Archive::listingPages('year'),
        ],
        'authors' => [
            'path' => 'author/{filename}',
            'extends' => '_layouts.listing',
            'items' => fn () => Archive::listingPages('author'),
        ],
    ],

    // Posts shown on a listing page.
    'postsFor' => fn ($page, $episodes) => $episodes->filter(fn ($post) => match ($page->kind) {
        'category' => collect($post->categories)->has($page->key),
        'tag' => collect($post->tags)->has($page->key),
        'month' => date('Y-m', $post->date) === $page->key,
        'year' => date('Y', $post->date) === $page->key,
        'author' => $post->author_slug === $page->key,
    }),

    // Internet Archive item for a post: ssr-podcast-NNN, or ssr-<slug> for unnumbered posts.
    'archiveIdentifier' => fn ($page) => $page->episode
        ? sprintf('ssr-podcast-%03d', $page->episode)
        : 'ssr-' . $page->slug,

    /**
     * Every audio file for a post, main file first. `src` is what the player and
     * download link use: the archive.org file, or locally the raw upload (see
     * bootstrap.php) so the site can be previewed before anything is uploaded.
     */
    'audioFiles' => function ($page) {
        if (! $page->audio_file) {
            return [];
        }

        $files = [[
            'file' => $page->audio_file,
            'title' => $page->audio_title,
            'size' => $page->audio_size,
            'duration' => $page->audio_duration,
            'original_url' => $page->audio_original_url,
            'url' => $page->audio_url,
        ]];
        foreach ($page->extra_audio ?? [] as $extra) {
            $files[] = is_array($extra) ? $extra : $extra->toArray();
        }

        return array_map(function ($file) use ($page) {
            $file['archive_url'] = $file['url']
                ?: "https://archive.org/download/{$page->archiveIdentifier()}/{$file['file']}";
            $file['src'] = $page->production
                ? $file['archive_url']
                : $page->baseUrl . '/local-audio/' . Str::after($file['original_url'], '/wp-content/uploads/');

            return (object) $file;
        }, $files);
    },

    'duration' => function ($page, $seconds) {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    },

    'megabytes' => fn ($page, $bytes) => number_format($bytes / 1048576, 1) . ' MB',

    'excerpt' => fn ($page, $length = 280) => Str::limit(
        trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($page->getContent()), ENT_QUOTES))),
        $length,
    ),

    // Filtered transcript (song lyrics removed) for the post's main audio file, if one exists.
    'transcript' => function ($page) {
        $file = __DIR__ . '/transcripts/' . pathinfo((string) $page->audio_file, PATHINFO_FILENAME) . '.txt';

        return $page->audio_file && is_file($file) ? file_get_contents($file) : null;
    },
];
