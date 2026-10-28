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
            <a href="{{ $audio->cloud_url }}" download>Download MP3</a>
            · {{ $post->duration($audio->duration) }} · {{ $post->megabytes($audio->size) }}
            @if ($audio->archive_page)
                · <a href="{{ $audio->archive_page }}">Also on the Internet Archive</a>
            @endif
        </p>
    </figure>
@endforeach
