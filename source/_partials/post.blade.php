{{-- $post: an episode; $heading: tag for the title (h1 on its own page, h2 in lists). --}}
<article class="post">
    <header class="post-header">
        <time class="date-badge" datetime="{{ date('Y-m-d', $post->date) }}">
            <span class="date-month">{{ date('M', $post->date) }}</span>
            <span class="date-day">{{ date('j', $post->date) }}</span>
            <span class="date-year">{{ date('Y', $post->date) }}</span>
        </time>
        <div>
            <{{ $heading }} class="post-title">
                @if ($heading === 'h1')
                    {{ $post->title }}
                @else
                    <a href="{{ $post->getUrl() }}">{{ $post->title }}</a>
                @endif
            </{{ $heading }}>
            <p class="meta">Posted by <a href="{{ $page->baseUrl }}/author/{{ $post->author_slug }}/">{{ $post->author }}</a> on {{ date('F j, Y', $post->date) }}</p>
        </div>
    </header>

    @include('_partials.player', ['post' => $post])

    <div class="post-body">
        {!! $post->getContent() !!}
    </div>

    <footer class="postinfo">
        <p>
            @if ($post->tags)
                <span class="label">Tagged</span>
                @foreach ($post->tags as $slug => $name)
                    <a href="{{ $page->baseUrl }}/tag/{{ $slug }}/">{{ $name }}</a>@if (! $loop->last), @endif
                @endforeach
            @endif
            @if ($post->categories)
                <span class="label">Posted under</span>
                @foreach ($post->categories as $slug => $name)
                    <a href="{{ $page->baseUrl }}/category/{{ $slug }}/">{{ $name }}</a>@if (! $loop->last), @endif
                @endforeach
            @endif
        </p>
        @if ($post->comments && $heading !== 'h1')
            <a class="postinfo-comments" href="{{ $post->getUrl() }}#comments">Comments ({{ count($post->comments) }})</a>
        @else
            <span class="postinfo-comments">Comments ({{ count($post->comments ?? []) }})</span>
        @endif
    </footer>
</article>
