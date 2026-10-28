@extends('_layouts.main')

@section('body')
    @include('_partials.post', ['post' => $page, 'heading' => 'h1'])

    @if ($transcript = $page->transcript())
        <details class="transcript">
            <summary>Transcript <span>(auto-generated, may contain errors; songs left out)</span></summary>
            @foreach (preg_split('/\n+/', trim($transcript)) as $line)
                <p>{{ $line }}</p>
            @endforeach
        </details>
    @endif

    @if ($page->comments)
        <section id="comments" class="comments">
            <h2>{{ count($page->comments) }} {{ count($page->comments) === 1 ? 'comment' : 'comments' }} <span>(closed)</span></h2>
            @foreach ($page->comments as $comment)
                <article class="comment">
                    <p class="meta"><strong>{{ $comment['name'] }}</strong> · <time datetime="{{ substr($comment['date'], 0, 10) }}">{{ date('F j, Y', strtotime($comment['date'])) }}</time></p>
                    <p>{!! nl2br(e($comment['text'])) !!}</p>
                </article>
            @endforeach
        </section>
    @endif

    <nav class="pager" aria-label="Older and newer posts">
        @if ($older = $page->getNext())
            <a rel="prev" href="{{ $older->getUrl() }}">← {{ $older->title }}</a>
        @endif
        @if ($newer = $page->getPrevious())
            <a rel="next" href="{{ $newer->getUrl() }}">{{ $newer->title }} →</a>
        @endif
    </nav>
@endsection
