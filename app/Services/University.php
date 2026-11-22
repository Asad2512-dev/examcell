<?php

namespace App\Services;

use DateTimeZone;

/**
 * The few rules that differ between universities, kept in one place and editable by the
 * administrator on the "Your university" page. Defaults match the original behaviour.
 */
class University
{
    public const DEFAULT_TERMS = ['Fall', 'Spring'];

    public const DEFAULT_SLOTS = ['09:00', '13:00', '17:00'];

    public static function name(): string
    {
        return (string) (Settings::get('university.name') ?: config('branding.name', 'ExamCell'));
    }

    public static function shortName(): string
    {
        return (string) (Settings::get('university.short_name') ?: config('branding.short_name', 'ExamCell'));
    }

    public static function timezone(): string
    {
        $zone = (string) Settings::get('university.timezone', '');

        return in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : (string) config('app.timezone', 'UTC');
    }

    /** @return list<string> */
    public static function terms(): array
    {
        $terms = array_values(array_filter(array_map('trim', (array) Settings::get('university.terms', self::DEFAULT_TERMS)), fn ($t) => $t !== ''));

        return $terms ?: self::DEFAULT_TERMS;
    }

    /** Start times (HH:MM) the automatic date sheet may use each day. @return list<string> */
    public static function slots(): array
    {
        $slots = array_values(array_filter((array) Settings::get('university.slots', self::DEFAULT_SLOTS), fn ($s) => is_string($s) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s)));

        return $slots ?: self::DEFAULT_SLOTS;
    }

    public static function maxGradePoint(): float
    {
        $value = (float) Settings::get('university.max_grade_point', 4);

        return $value > 0 && $value <= 99 ? $value : 4.0;
    }

    public static function maxDutiesPerDay(): int
    {
        $value = (int) Settings::get('university.max_duties_per_day', config('examcell.max_duties_per_day', 2));

        return $value >= 1 && $value <= 10 ? $value : 2;
    }

    /** Apply the saved profile to the running application (called once per request). */
    public static function apply(): void
    {
        $zone = self::timezone();
        config(['app.timezone' => $zone]);
        date_default_timezone_set($zone);
        config(['branding.short_name' => self::shortName(), 'branding.name' => self::name(), 'examcell.max_duties_per_day' => self::maxDutiesPerDay()]);
    }
}
