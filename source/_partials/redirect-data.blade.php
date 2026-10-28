{{-- Lookup tables for the old-URL redirect scripts. Paths are relative to the site root. --}}
@php
    $basePath = rtrim((string) parse_url($page->baseUrl, PHP_URL_PATH), '/');
    $postPath = fn ($post) => $basePath . '/' . date('Y/m/d', $post->date) . '/' . $post->slug . '/';
    $redirects = [
        'base' => $basePath,
        'slugs' => $episodes->mapWithKeys(fn ($post) => [$post->slug => $postPath($post)]),
        'ids' => $episodes->mapWithKeys(fn ($post) => [$post->wp_id => $postPath($post)]),
        'authors' => [2 => 'rich', 3 => 'nariman'],
        'audio' => $episodes->flatMap(fn ($post) => collect($post->audioFiles())
            ->mapWithKeys(fn ($audio) => [strtolower($audio->file) => $audio->cloud_url])),
    ];
@endphp
<script>var SSR = {!! json_encode($redirects, JSON_UNESCAPED_SLASHES) !!};</script>
