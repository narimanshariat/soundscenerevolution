<?php

// Router for previewing build_local with PHP's built-in server, mimicking GitHub
// Pages: real files and directory indexes are served as-is, anything else gets
// 404.html with a 404 status (so the old-URL redirect script can be tested).
//
//     docker compose run --rm --service-ports php php -S 0.0.0.0:8000 -t build_local scripts/dev-router.php

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $_SERVER['DOCUMENT_ROOT'] . $path;

if (is_file($file) || is_file(rtrim($file, '/') . '/index.html')) {
    return false;
}

http_response_code(404);
readfile($_SERVER['DOCUMENT_ROOT'] . '/404.html');
