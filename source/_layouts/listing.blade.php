@extends('_layouts.main')

@section('body')
    @php $posts = $page->postsFor($episodes); @endphp
    <h1 class="listing-title">{{ $page->heading }}</h1>
    <p class="listing-count">{{ $posts->count() }} {{ $posts->count() === 1 ? 'post' : 'posts' }}</p>

    <ol class="listing">
        @foreach ($posts as $post)
            <li>
                <a class="listing-link" href="{{ $post->getUrl() }}">{{ $post->title }}</a>
                <span class="meta"><time datetime="{{ date('Y-m-d', $post->date) }}">{{ date('M j, Y', $post->date) }}</time>
                    @if ($post->audio_duration) · {{ $post->duration($post->audio_duration) }} @endif</span>
                <p>{{ $post->excerpt(200) }}</p>
            </li>
        @endforeach
    </ol>
@endsection
