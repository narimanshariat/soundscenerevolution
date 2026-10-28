{{-- Podcast feed. Built to /feed/index.html and copied to /feed.xml by bootstrap.php. --}}
@php
    $items = $episodes->filter(fn ($e) => $e->audio_file);
    $cover = $page->baseUrl . '/assets/images/wp-content/uploads/2009/05/ssrfinal_square.jpg';
    $summary = 'The SSR show is comprised of interviews with local bands in the SF Bay Area. We also review new music and update you with local news. Archived 2005-2010; the music belongs to the bands.';
@endphp
{!! '<' . '?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>Sound Scene Revolution</title>
    <link>{{ $page->baseUrl }}/</link>
    <atom:link href="{{ $page->baseUrl }}/feed.xml" rel="self" type="application/rss+xml"/>
    <description>{{ $summary }}</description>
    <language>en-us</language>
    <copyright>2005-2010 Sound Scene Revolution. Music belongs to the respective bands.</copyright>
    <lastBuildDate>{{ gmdate(DATE_RSS, $items->first()->date_gmt) }}</lastBuildDate>
    <image>
        <url>{{ $cover }}</url>
        <title>Sound Scene Revolution</title>
        <link>{{ $page->baseUrl }}/</link>
    </image>
    <itunes:new-feed-url>{{ $page->baseUrl }}/feed.xml</itunes:new-feed-url>
    <itunes:author>Sound Scene Revolution</itunes:author>
    <itunes:subtitle>News and Interviews with Local Bands</itunes:subtitle>
    <itunes:summary>{{ $summary }}</itunes:summary>
    <itunes:image href="{{ $cover }}"/>
    <itunes:category text="Music"/>
    <itunes:explicit>true</itunes:explicit>
    <itunes:type>episodic</itunes:type>
    <itunes:complete>Yes</itunes:complete>
@foreach ($items as $e)
@php $audio = $e->audioFiles()[0]; @endphp
    <item>
        <title>{{ $e->title }}</title>
        <link>{{ $e->getUrl() }}</link>
        <guid isPermaLink="false">{{ $e->guid }}</guid>
        <pubDate>{{ gmdate(DATE_RSS, $e->date_gmt) }}</pubDate>
        <description>{{ $e->excerpt(600) }}</description>
        <content:encoded><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', $e->getContent()) !!}]]></content:encoded>
        <enclosure url="{{ $audio->cloud_url }}" length="{{ $audio->size }}" type="audio/mpeg"/>
        <itunes:author>{{ $e->author }}</itunes:author>
        <itunes:duration>{{ $audio->duration }}</itunes:duration>
@if ($e->episode)
        <itunes:episode>{{ $e->episode }}</itunes:episode>
@endif
        <itunes:episodeType>full</itunes:episodeType>
        <itunes:explicit>true</itunes:explicit>
    </item>
@endforeach
</channel>
</rss>
