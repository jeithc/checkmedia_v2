<?php

$mediaDisk = env('MEDIA_DISK', 'public');

return [
    'disk' => $mediaDisk,

    'visibility' => env('MEDIA_VISIBILITY', env('AWS_VISIBILITY', $mediaDisk === 's3' ? 'private' : 'public')),

    'temporary_urls' => env('MEDIA_TEMPORARY_URLS', $mediaDisk === 's3'),

    'temporary_url_ttl_minutes' => (int) env('MEDIA_TEMPORARY_URL_TTL_MINUTES', 30),
];
