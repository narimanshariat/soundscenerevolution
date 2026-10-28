---
title: All episodes
---
@extends('_layouts.main')

@section('body')
    @php
        $numbered = $episodes->filter(fn ($e) => $e->episode)->sortBy('episode');
        $others = $episodes->reject(fn ($e) => $e->episode)->sortBy('date');
    @endphp
    <h1 class="listing-title">All episodes</h1>
    <p class="listing-count">{{ $numbered->count() }} episodes, {{ date('F Y', $numbered->first()->date) }} to {{ date('F Y', $numbered->last()->date) }}</p>

    <table class="episodes">
        <thead>
            <tr><th scope="col">#</th><th scope="col">Episode</th><th scope="col">Date</th><th scope="col">Length</th></tr>
        </thead>
        <tbody>
            @foreach ($numbered as $e)
                <tr>
                    <td>{{ $e->episode }}</td>
                    <td><a href="{{ $e->getUrl() }}">{{ preg_replace('/\s*-\s*Podcast \d+$/', '', $e->title) }}</a></td>
                    <td><time datetime="{{ date('Y-m-d', $e->date) }}">{{ date('M j, Y', $e->date) }}</time></td>
                    <td>{{ $e->duration($e->audio_duration) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Other posts</h2>
    <ul class="plain">
        @foreach ($others as $e)
            <li><a href="{{ $e->getUrl() }}">{{ $e->title }}</a> <span class="meta">{{ date('M j, Y', $e->date) }}</span></li>
        @endforeach
    </ul>
@endsection
