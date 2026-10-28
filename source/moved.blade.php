---
title: Where things went
---
@extends('_layouts.main')

@section('body')
    <h1 class="listing-title">Where things went</h1>
    <p>The old site ran on WordPress and served everything itself. This archive is static, so a few kinds of address work differently now. Posts, categories, tags, monthly archives and homepage pages are all still at their original addresses.</p>

    <table class="moved">
        <thead><tr><th scope="col">Old address</th><th scope="col">Now</th></tr></thead>
        <tbody>
            <tr><td><code>/YYYY/MM/DD/post-name/</code></td><td>Unchanged</td></tr>
            <tr><td><code>/category/…/</code>, <code>/tag/…/</code>, <code>/YYYY/MM/</code>, <code>/page/N/</code></td><td>Unchanged. Each category, tag and month is now a single page instead of several.</td></tr>
            <tr><td><code>/feed/</code></td><td>Still works. The same podcast feed is also at <a href="{{ $page->baseUrl }}/feed.xml"><code>/feed.xml</code></a>, the better address to subscribe to.</td></tr>
            <tr><td><code>feeds2.feedburner.com/SSR</code></td><td>The old FeedBurner feed. Subscribe to <a href="{{ $page->baseUrl }}/feed.xml"><code>/feed.xml</code></a> instead.</td></tr>
            <tr><td><code>/?p=123</code></td><td>Sent on to the post</td></tr>
            <tr><td><code>/wp-content/uploads/audio/….mp3</code> and <code>/podpress_trac/…</code></td><td>The Internet Archive; see the list below</td></tr>
            <tr><td><code>/author/rich/</code>, <code>/author/nariman/</code></td><td>Unchanged</td></tr>
            <tr><td>Search</td><td>Gone; browse <a href="{{ $page->baseUrl }}/episodes/">all episodes</a> or the tags instead</td></tr>
            <tr><td>Comments</td><td>Closed. The old ones are kept on each post.</td></tr>
        </tbody>
    </table>

    <h2 id="audio">Every audio file</h2>
    <p>Each episode is its own item at the Internet Archive, where it can be streamed, downloaded or found in other formats.</p>
    <table class="moved audio-moved">
        <thead><tr><th scope="col">Episode</th><th scope="col">Old file</th><th scope="col">Now at the Internet Archive</th></tr></thead>
        <tbody>
            @foreach ($episodes->filter(fn ($e) => $e->audio_file)->sortBy(fn ($e) => [$e->episode ?? 0, $e->date]) as $e)
                @foreach ($e->audioFiles() as $audio)
                    <tr>
                        <td><a href="{{ $e->getUrl() }}">{{ $e->episode ? 'Podcast ' . $e->episode : $e->title }}</a></td>
                        <td><code>{{ \Illuminate\Support\Str::after($audio->original_url, 'soundscenerevolution.com') }}</code></td>
                        <td><a href="{{ $audio->archive_url }}">{{ $e->archiveIdentifier() }}/{{ $audio->file }}</a></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@endsection
