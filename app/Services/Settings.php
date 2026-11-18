<?php

namespace App\Services;

use App\Models\AppSetting;

/** Small admin-editable key/value store (JSON). Falls back to the supplied default or config. */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $memoKey = 'settings.resolved.'.$key;
        $attributes = request()->attributes;
        $memoize = request()->route() !== null;
        if ($memoize && $attributes->has($memoKey)) {
            return $attributes->get($memoKey) ?? $default;
        }
        try {
            $value = AppSetting::find($key)?->value['v'] ?? null;
            if ($memoize) {
                $attributes->set($memoKey, $value);
            }

            return $value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
        request()->attributes->remove('settings.resolved.'.$key);
    }

    /** Notification events an admin can switch off, with the notice titles each covers. */
    public const EVENTS = [
        'schedule' => ['Exam schedule published', ['Exam schedule published']],
        'results' => ['Results published or corrected', ['Results published', 'Published result corrected']],
        'duties' => ['Duty assignments and cover', ['Duty assigned', 'Replacement duty assigned', 'Duty request approved', 'Duty request rejected', 'Duty request submitted']],
        'duty_requests' => ['Admin alerts for duty requests and AI suggestions', ['48-hour duty request', 'AI replacement suggestion']],
        'marks' => ['Marks returned to teachers', ['Marks returned']],
        'rechecks' => ['Rechecking decisions', ['Recheck updated']],
        'admit' => ['Admit card generation', ['Admit card generation completed', 'Admit card generation failed']],
    ];

    public static function noticeEnabled(string $title): bool
    {
        foreach (self::EVENTS as $key => [$label, $titles]) {
            if (in_array($title, $titles, true)) {
                return (bool) self::get('notify.'.$key, true);
            }
        }

        return true;
    }
}
