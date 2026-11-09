<?php

return [
    'content_path' => resource_path('content'),
    'content_cache_minutes' => 60,
    'frontend_sections' => ['common', 'navigation', 'authentication', 'landing'],
    'color_tokens' => [
        'Primary action' => '--primary', 'Accent' => '--accent', 'Page background' => '--bg',
        'Card background' => '--surface', 'Main text' => '--text', 'Secondary text' => '--muted',
        'Borders' => '--border', 'Success' => '--success', 'Warning' => '--warning', 'Error' => '--danger',
    ],
    'dark_color_tokens' => [
        'Primary action' => '--primary', 'Accent' => '--accent', 'Page background' => '--bg',
        'Card background' => '--surface', 'Main text' => '--text', 'Secondary text' => '--muted',
        'Borders' => '--border', 'Success' => '--success', 'Warning' => '--warning', 'Error' => '--danger',
    ],
];
