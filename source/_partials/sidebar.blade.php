@php
    $categories = \Ssr\Archive::terms('categories');
    $tags = \Ssr\Archive::terms('tags');
    $maxTag = max(array_column($tags, 'count'));
    // Old accounts, linked as they were plus a Wayback Machine copy for the ones that are gone.
    $elsewhere = [
        ['MySpace', 'http://www.myspace.com/soundscenerevolution'],
        ['Twitter', 'http://twitter.com/soundscenerev'],
        ['Flickr photos', 'http://www.flickr.com/photos/soundscenerevolution'],
    ];
    $friends = [
        ['Punknews.org', 'http://www.punknews.org/'],
        ['Issue Oriented', 'http://www.issueoriented.com/'],
        ['NASCENT', 'http://www.nascentmag.com/'],
        ['Adam D', 'http://xadamdx.blogspot.com/'],
        ['Happy Harvest Farms', 'http://www.happyharvestfarms.com'],
    ];
@endphp
<aside class="sidebar" aria-label="Archive">
    <section>
        <h2>Categories</h2>
        <ul class="plain">
            @foreach ($categories as $slug => $category)
                <li><a href="{{ $page->baseUrl }}/category/{{ $slug }}/">{{ $category['name'] }}</a> <span class="count">({{ $category['count'] }})</span></li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2>Archives</h2>
        <dl class="months">
            @foreach (\Ssr\Archive::months() as $year => $months)
                <dt><a href="{{ $page->baseUrl }}/{{ $year }}/">{{ $year }}</a></dt>
                <dd>
                    @foreach ($months as $month => $count)
                        <a href="{{ $page->baseUrl }}/{{ $year }}/{{ $month }}/" title="{{ $count }} {{ $count === 1 ? 'post' : 'posts' }}">{{ date('M', mktime(0, 0, 0, (int) $month, 1)) }}</a>
                    @endforeach
                </dd>
            @endforeach
        </dl>
    </section>

    <section>
        <h2>Tags</h2>
        <p class="tag-cloud">
            @foreach ($tags as $slug => $tag)
                <a href="{{ $page->baseUrl }}/tag/{{ $slug }}/" style="font-size: {{ round(0.85 + 0.75 * log($tag['count']) / log($maxTag), 2) }}em">{{ $tag['name'] }}</a>
            @endforeach
        </p>
    </section>

    <section>
        <h2>We're on the Internet</h2>
        <ul class="plain">
            @foreach ($elsewhere as [$name, $url])
                <li><a href="{{ $url }}">{{ $name }}</a> <a class="archived" href="https://web.archive.org/web/2010/{{ $url }}">archived copy</a></li>
            @endforeach
        </ul>
        <h2>Check it</h2>
        <ul class="plain">
            @foreach ($friends as [$name, $url])
                <li><a href="{{ $url }}">{{ $name }}</a> <a class="archived" href="https://web.archive.org/web/2010/{{ $url }}">archived copy</a></li>
            @endforeach
        </ul>
    </section>
</aside>
