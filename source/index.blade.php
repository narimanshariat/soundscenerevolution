---
pagination:
    collection: episodes
    perPage: 10
    prefix: page
---
@extends('_layouts.main')

@push('head')
    @include('_partials.query-redirects')
@endpush

@section('body')
    @if ($pagination->currentPage === 1)
        <section class="about" id="about">
            <h2>About this archive</h2>
            <p>Sound Scene Revolution was a podcast out of the San Jose / SF Bay Area, made from 2005 to 2010: {{ \Ssr\Archive::terms('categories')['podcast']['count'] }} episodes of interviews with punk, hardcore, indie and folk bands, plus a few live recordings. Rich hosted and wrote most of the posts; Sound Guy Nariman ran the sound.</p>
            <p>This is a permanent, read-only copy of the original site. Every post is at its original address, with its original text and the comments people left. The audio lives at the <a href="https://archive.org/">Internet Archive</a>. The music in these episodes belongs to the bands. <a href="{{ $page->baseUrl }}/episodes/">Browse all episodes</a> or <a href="{{ $page->baseUrl }}/moved/">see where things went</a>.</p>
        </section>
    @endif

    @foreach ($pagination->items as $post)
        @include('_partials.post', ['post' => $post, 'heading' => 'h2'])
    @endforeach

    <nav class="pager" aria-label="Pages">
        @if ($pagination->next)
            <a rel="next" href="{{ $page->baseUrl }}{{ $pagination->next }}/">← Older posts</a>
        @endif
        <span>Page {{ $pagination->currentPage }} of {{ $pagination->totalPages }}</span>
        @if ($pagination->previous)
            <a rel="prev" href="{{ $page->baseUrl }}{{ rtrim($pagination->previous, "/") }}/">Newer posts →</a>
        @endif
    </nav>
@endsection
