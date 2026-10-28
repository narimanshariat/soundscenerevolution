<?php

use Illuminate\Container\Container;
use TightenCo\Jigsaw\Events\EventBus;
use TightenCo\Jigsaw\Jigsaw;

/** @var Container $container */
/** @var EventBus $events */

// Post dates are the old site's wall-clock times; keep them as written.
date_default_timezone_set('UTC');

$events->afterBuild(function (Jigsaw $jigsaw) {
    $dest = $jigsaw->getDestinationPath();

    // GitHub Pages serves /404.html for missing pages; Jigsaw writes pretty URLs.
    rename("$dest/404/index.html", "$dest/404.html");
    rmdir("$dest/404");

    // /feed/ keeps old subscribers working but is served as text/html;
    // /feed.xml is the same feed with a proper XML content type.
    copy("$dest/feed/index.html", "$dest/feed.xml");

    // Local preview only: play audio straight from the raw uploads.
    if (! $jigsaw->getConfig('production') && is_dir('/raw/uploads/wp-content/uploads')) {
        @unlink("$dest/local-audio");
        symlink('/raw/uploads/wp-content/uploads', "$dest/local-audio");
    }
});
