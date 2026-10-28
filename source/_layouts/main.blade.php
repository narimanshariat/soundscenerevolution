<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title === 'Sound Scene Revolution' ? $page->title : $page->title . ' · Sound Scene Revolution' }}</title>
    <meta name="description" content="{{ $page->wp_id ? $page->excerpt(160) : $page->description }}">
    <link rel="canonical" href="{{ $page->getUrl() }}">
    <link rel="alternate" type="application/rss+xml" title="Sound Scene Revolution podcast feed" href="{{ $page->baseUrl }}/feed.xml">
    <link rel="icon" href="{{ $page->baseUrl }}/assets/images/wp-content/uploads/2009/05/ssrfinal_square-150x150.jpg">
    <meta name="theme-color" content="#02141c">
    <link rel="stylesheet" href="{{ $page->baseUrl }}/assets/css/main.css">
    @stack('head')
</head>
<body>
    <div class="page">
    <header class="site-header">
        <a class="banner" href="{{ $page->baseUrl }}/">
            <img src="{{ $page->baseUrl }}/assets/images/ssr-banner.jpg" alt="Sound Scene Revolution" width="1000" height="281">
        </a>
        <nav class="menu" aria-label="Main">
            <a href="{{ $page->baseUrl }}/">Home</a>
            <a href="{{ $page->baseUrl }}/episodes/">All episodes</a>
            <a href="{{ $page->baseUrl }}/moved/">Where things went</a>
            <a href="{{ $page->baseUrl }}/feed.xml">Podcast feed</a>
            <span class="menu-note">{{ $page->tagline }}</span>
        </nav>
    </header>

    <div class="layout">
        <main id="content">
            @yield('body')
        </main>
        @include('_partials.sidebar')
    </div>

    <footer class="site-footer">
        <p>Sound Scene Revolution, 2005–2010. A read-only archive: comments are closed and nothing new is being added.
        Audio is preserved at the <a href="https://archive.org/search?query=subject%3Asoundscenerevolution">Internet Archive</a>.
        The music in these episodes belongs to the bands.</p>
    </footer>
    </div>
</body>
</html>
