<?php

namespace App\Support;

class ScreenBindings
{
    public static function module(array $page): string
    {
        $s = $page['slug'];
        if ($page['kind'] === 'auth') {
            return 'auth';
        }
        if ($page['kind'] === 'dashboard') {
            return 'dashboard';
        }
        if (in_array($s, ['ai-automation', 'ai-settings'], true)) {
            return 'ai';
        }
        if ($s === 'notification-settings') {
            return 'settings-notifications';
        }
        if ($s === 'account-and-permissions') {
            return 'settings-accounts';
        }
        if (str_contains($s, 'assistant')) {
            return 'chat';
        }
        if (str_contains($s, 'notification')) {
            return 'notifications';
        }
        if (str_contains($s, 'import')) {
            return 'import';
        }
        if (str_contains($s, 'recheck')) {
            return 'rechecks';
        }
        if (str_contains($s, 'admit') || str_contains($s, 'bulk-generation')) {
            return 'admit';
        }
        if (str_contains($s, 'duty') || str_contains($s, 'duties') || str_contains($s, 'replacement') || str_contains($s, 'availability') || str_contains($s, 'unavailability') || str_contains($s, 'reserve-teacher')) {
            return 'duties';
        }
        if (str_contains($s, 'seat') || str_contains($s, 'room-capacity') || str_contains($s, 'regeneration')) {
            return 'seats';
        }
        if (str_contains($s, 'date-sheet') || str_contains($s, 'timetable') || str_contains($s, 'schedule') || str_contains($s, 'conflict-resolution')) {
            return 'schedule';
        }
        if (str_contains($s, 'correction') || str_contains($s, 'result-editing') || str_contains($s, 'previous-versus')) {
            return 'corrections';
        }
        if (str_contains($s, 'marks') || str_contains($s, 'submission')) {
            return 'marks';
        }
        if (str_contains($s, 'analytics') || str_contains($s, 'analysis') || str_contains($s, 'anomaly') || str_contains($s, 'finding') || str_contains($s, 'prediction') || str_contains($s, 'missing-result') || str_contains($s, 'incomplete-result') || str_contains($s, 'conflict-detection')) {
            return 'analytics';
        }
        if (str_contains($s, 'history') || str_contains($s, 'audit') || str_contains($s, 'activity')) {
            return 'audit';
        }
        if (str_contains($s, 'result') || str_contains($s, 'gpa') || str_contains($s, 'weighted-score') || str_contains($s, 'publication') || str_contains($s, 'report')) {
            return 'results';
        }
        if ($page['role'] !== 'admin' && str_contains($s, 'profile')) {
            return 'profile';
        }
        if ($page['role'] !== 'admin' && (str_contains($s, 'course') || str_contains($s, 'student') || str_contains($s, 'roster'))) {
            return 'courses';
        }
        if (in_array($s, ['backup-status', 'unauthorized-access', 'session-expired', 'loading-records', 'empty-records', 'server-error', 'permission-denied', 'success-confirmation', 'password-reset-confirmation'])) {
            return 'state';
        }

        return 'academic';
    }

    public static function resource(array $page): string
    {
        $s = $page['slug'];

        return match (true) {
            str_contains($s, 'department') => 'departments',str_contains($s, 'session') => 'sessions',str_contains($s, 'semester') => 'semesters',str_contains($s, 'section') => 'sections',str_contains($s, 'enrollment') => 'enrollments',str_contains($s, 'teacher-course') => 'teacher-assignments',str_contains($s, 'boundary') || str_contains($s, 'grade-point') => 'boundaries',str_contains($s, 'component') || str_contains($s, 'weight') => 'components',str_contains($s, 'grading') || str_contains($s, 'gpa') => 'grading',str_contains($s, 'room') => 'rooms',str_contains($s, 'credit') || str_contains($s, 'course') => 'courses',str_contains($s, 'examination') || str_contains($s, 'exam-type') => 'examinations',str_contains($s, 'teacher-directory') || str_contains($s, 'user') || str_contains($s, 'account') => 'users',str_contains($s, 'student') => 'students',default => 'departments'
        };
    }
}
