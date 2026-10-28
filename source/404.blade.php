---
title: Page not found
---
@extends('_layouts.main')

@push('head')
    @include('_partials.redirect-data')
    <script>
    // Old WordPress addresses that have no static page: audio files, podPress
    // download links, feeds, paged archives and post sub-pages.
    (function () {
        var path = location.pathname, to = null, m;
        try { path = decodeURIComponent(path); } catch (e) {}
        if (SSR.base && path.indexOf(SSR.base) === 0) path = path.slice(SSR.base.length);
        var file = path.split('/').pop().toLowerCase();

        if (/\.mp3$/.test(file)) to = SSR.audio[file] || null;
        else if ((m = path.match(/^\/wp-content\/uploads\/(\d{4}\/\d{2}\/[^/]+\.(?:jpe?g|png|gif))$/i))) to = SSR.base + '/assets/images/wp-content/uploads/' + m[1];
        else if ((m = path.match(/^\/\d{4}\/\d{2}\/\d{2}\/([^/]+)\/./))) to = SSR.slugs[m[1]] || null;
        else if ((m = path.match(/^\/(\d{4})\/(\d{2})\/\d{2}\/?$/))) to = SSR.base + '/' + m[1] + '/' + m[2] + '/';
        else if ((m = path.match(/^\/((?:category|tag|author)\/[^/]+|\d{4}(?:\/\d{2})?)\/(?:page\/\d+|feed)\b/))) to = SSR.base + '/' + m[1] + '/';
        else if (/^\/(?:feed\/.+|comments\/feed\/?|wp-(?:rss2?|atom|commentsrss2)\.php)$/i.test(path)) to = SSR.base + '/feed.xml';
        else if (/^\/page\/\d+\/?$/.test(path)) to = SSR.base + '/';
        else to = SSR.slugs[path.replace(/\/+$/, '').split('/').pop()] || null;

        if (to) location.replace(to);
    })();
    </script>
@endpush

@section('body')
    <h1 class="listing-title">Page not found</h1>
    <p>This archive keeps every address from the old soundscenerevolution.com, but nothing lives here. If you followed an old link to an episode or an MP3, you should have been sent on automatically.</p>
    <ul>
        <li><a href="{{ $page->baseUrl }}/episodes/">Browse all episodes</a></li>
        <li><a href="{{ $page->baseUrl }}/moved/">See where everything from the old site went</a>, including every audio file</li>
        <li><a href="{{ $page->baseUrl }}/">Go to the homepage</a></li>
    </ul>
@endsection
