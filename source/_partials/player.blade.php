{{-- $post: an episode. Renders a native player and download link for each of its audio files. --}}
@foreach ($post->audioFiles() as $i => $audio)
    <figure class="player">
        @if ($i > 0)
            <figcaption>{{ $audio->title }}</figcaption>
        @endif
        <audio controls preload="none" src="{{ $audio->cloud_url }}">
            <a href="{{ $audio->cloud_url }}">Download {{ $audio->file }}</a>
        </audio>
        <p class="player-meta">
            Download: <a href="{{ $audio->cloud_url }}" download>Cloudflare</a>
            @if ($audio->archive_url)
                | <a href="{{ $audio->archive_url }}" download>Internet Archive</a>
            @endif
            · {{ $post->duration($audio->duration) }} · {{ $post->megabytes($audio->size) }}
        </p>
    </figure>
@endforeach
