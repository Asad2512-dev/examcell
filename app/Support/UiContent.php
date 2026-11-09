<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class UiContent
{
    /** @return array<string, mixed> */
    public function section(string $section): array
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $section)) {
            return [];
        }

        $load = function () use ($section) {
            $content = $this->read($section);
            foreach ((array) data_get($this->overrides(), 'content.'.$section, []) as $key => $value) {
                if (is_string($value)) {
                    data_set($content, $key, $value);
                }
            }
            return $content;
        };
        try {
            return Cache::remember(
                'ui-content.'.$section,
                now()->addMinutes((int) config('ui.content_cache_minutes', 60)),
                $load,
            );
        } catch (Throwable) {
            return $load();
        }
    }

    public function text(string $key, array $replace = [], ?string $fallback = null): string
    {
        [$section, $path] = array_pad(explode('.', $key, 2), 2, '');
        $value = $this->section($section);

        foreach (explode('.', $path) as $part) {
            if ($part === '' || ! is_array($value) || ! array_key_exists($part, $value)) {
                return $fallback ?? $key;
            }
            $value = $value[$part];
        }

        if (! is_string($value)) {
            return $fallback ?? $key;
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':'.ucfirst((string) $name), (string) $replacement, $value);
        }

        return $value;
    }

    /** The frontend receives only the explicitly approved public content sections. */
    public function frontend(): array
    {
        $content = [];
        foreach ((array) config('ui.frontend_sections', ['common', 'navigation']) as $section) {
            $content[$section] = $this->section((string) $section);
        }

        return $content;
    }

    /** @return array<string, mixed> */
    public function overrides(): array
    {
        try {
            $decoded = json_decode(Storage::disk('local')->get('ui-overrides.json'), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function read(string $section): array
    {
        $path = rtrim((string) config('ui.content_path', resource_path('content')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$section.'.json';
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
