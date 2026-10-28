{{-- $post: an episode. Renders a native player and download link for each of its audio files. --}}
@foreach ($post->audioFiles() as $i => $audio)
    <figure class="player">
        @if ($i > 0)
            <figcaption>{{ $audio->title }}</figcaption>
        @endif
        <audio controls preload="none" src="{{ $audio->src }}">
            <a href="{{ $audio->src }}">Download {{ $audio->file }}</a>
        </audio>
        <p class="player-meta">
            <a href="{{ $audio->src }}" download>Download MP3</a>
            · {{ $post->duration($audio->duration) }} · {{ $post->megabytes($audio->size) }}
        </p>
    </figure>
@endforeach
