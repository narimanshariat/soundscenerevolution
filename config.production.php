<?php

return [
    'production' => true,
    // SITE_URL lets the deploy workflow build for the github.io review address
    // before DNS moves; without it the site is built for its real domain.
    'baseUrl' => getenv('SITE_URL') ?: 'https://soundscenerevolution.com',
];
