<?php

namespace App\Http\Controllers;

use App\Support\UiContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AppearanceController
{
    public function index(Request $request, UiContent $content)
    {
        $sections = collect(glob(resource_path('content/*.json')) ?: [])
            ->map(fn ($path) => basename($path, '.json'))->sort()->values();
        $section = $request->query('section', 'common');
        abort_unless($sections->contains($section), 404);

        $flat = [];
        $this->flatten($content->section($section), '', $flat);
        $overrides = $content->overrides();
        $light = [];
        foreach ((array) config('ui.color_tokens') as $label => $variable) {
            $light[$label] = data_get($overrides, 'colors.light.'.$variable, $this->tokenValue('light', $variable));
        }
        $dark = [];
        foreach ((array) config('ui.dark_color_tokens') as $label => $variable) {
            $dark[$label] = data_get($overrides, 'colors.dark.'.$variable, $this->tokenValue('dark', $variable));
        }

        $palette = $this->paletteDefaults();
        foreach ((array) data_get($overrides, 'colors.palette', []) as $key => $value) {
            if (array_key_exists($key, $palette)) $palette[$key] = $value;
        }

        return view('admin.appearance', compact('sections', 'section', 'flat', 'light', 'dark', 'palette'));
    }

    public function save(Request $request, UiContent $content)
    {
        $section = $request->input('section');
        abort_unless(is_string($section) && preg_match('/^[a-z][a-z0-9_-]*$/', $section)
            && is_file(resource_path('content/'.$section.'.json')), 404);

        $data = $content->section($section);
        $allowed = [];
        $this->flatten($data, '', $allowed);
        $submitted = $request->input('content', []);
        if (! is_array($submitted) || count($submitted) > 3000) {
            throw ValidationException::withMessages(['content' => 'Choose a content section and try again.']);
        }

        $overrides = $content->overrides();
        foreach ($submitted as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $allowed) || ! is_string($value) || mb_strlen($value) > 5000) {
                throw ValidationException::withMessages(['content' => 'One of the wording fields is invalid or too long.']);
            }
            $overrides['content'][$section][$key] = $value;
        }

        $palette = $this->paletteDefaults();
        foreach (['light_colors' => 'light', 'dark_colors' => 'dark'] as $input => $theme) {
            $values = $request->input($input, []);
            if (! is_array($values)) continue;
            $labels = (array) config($theme === 'light' ? 'ui.color_tokens' : 'ui.dark_color_tokens');
            foreach ($labels as $label => $variable) {
                if (isset($values[$label]) && $this->validColor($values[$label])) {
                    $overrides['colors'][$theme][$variable] = strtoupper($values[$label]);
                }
            }
        }
        foreach ((array) $request->input('palette_colors', []) as $key => $value) {
            if (array_key_exists((string) $key, $palette) && $this->validColor($value)) {
                $overrides['colors']['palette'][$key] = strtolower($value);
            }
        }

        Storage::disk('local')->put('ui-overrides.json', json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        foreach (glob(resource_path('content/*.json')) ?: [] as $path) {
            Cache::forget('ui-content.'.basename($path, '.json'));
        }
        return redirect()->route('admin.appearance', ['section' => $section])->with('status', 'Your appearance and wording changes are saved.');
    }

    public function paletteCss(UiContent $content)
    {
        $overrides = $content->overrides();
        $palette = $this->paletteDefaults();
        foreach ((array) data_get($overrides, 'colors.palette', []) as $key => $value) {
            if (array_key_exists($key, $palette) && $this->validColor($value)) $palette[$key] = $value;
        }
        $css = ':root{';
        foreach ($palette as $key => $value) $css .= '--palette-'.$key.':'.$value.';';
        $css .= '}';
        foreach (['light' => 'ui.color_tokens', 'dark' => 'ui.dark_color_tokens'] as $theme => $path) {
            $selector = $theme === 'dark' ? '.dark' : ':root';
            $css .= $selector.'{';
            foreach ((array) config($path) as $variable) {
                $value = data_get($overrides, 'colors.'.$theme.'.'.$variable);
                if ($this->validColor($value)) $css .= $variable.':'.$value.';';
            }
            $css .= '}';
        }

        return response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }

    private function flatten(array $data, string $prefix, array &$flat): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) $this->flatten($value, $path, $flat);
            elseif (is_string($value)) $flat[$path] = $value;
        }
    }

    private function paletteDefaults(): array
    {
        preg_match_all('/--palette-([a-f0-9]+):\s*(#[a-f0-9]{3,8})\s*;/i', (string) file_get_contents(resource_path('css/design-system/palette.css')), $matches, PREG_SET_ORDER);
        $palette = [];
        foreach ($matches as $match) $palette[$match[1]] = strtolower($match[2]);
        return $palette;
    }

    private function tokenValue(string $theme, string $variable): string
    {
        $file = resource_path('css/design-system/'.($theme === 'dark' ? 'themes.css' : 'colors.css'));
        $css = (string) file_get_contents($file);
        if (! preg_match('/(?<![\w-])'.preg_quote($variable, '/').':\s*([^;]+)/', $css, $match)) return '#3538cd';
        $value = trim($match[1]);
        if (preg_match('/var\(--palette-([a-f0-9]+)\)/i', $value, $paletteMatch)) {
            return $this->paletteDefaults()[$paletteMatch[1]] ?? '#3538cd';
        }
        return $this->validColor($value) ? $value : '#3538cd';
    }

    private function validColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value) === 1;
    }
}
