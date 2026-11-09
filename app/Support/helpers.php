<?php

use App\Support\UiContent;

if (! function_exists('ui_text')) {
    function ui_text(string $key, array $replace = [], ?string $fallback = null): string
    {
        return app(UiContent::class)->text($key, $replace, $fallback);
    }
}

if (! function_exists('ui_frontend_content')) {
    /** @return array<string, mixed> */
    function ui_frontend_content(): array
    {
        return app(UiContent::class)->frontend();
    }
}
