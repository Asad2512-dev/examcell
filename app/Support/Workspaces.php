<?php

namespace App\Support;

/**
 * The short, flat navigation. Each workspace owns tabs; every legacy screen slug maps to a
 * workspace + tab so old URLs redirect and no backend logic, route or permission is removed.
 */
final class Workspaces
{
    /** Pages that keep their own URL (bell, shared record editors, form-redirect targets). */
    public const DIRECT = ['records', 'duty-replacements', 'exam-schedule', 'my-duties', 'recheck-requests', 'forecast', 'notification-center', 'teacher-notifications', 'student-notifications'];

    public static function definition(string $role): array
    {
        $t = fn (string $key, string $label, string $slug, array $pills = []) => compact('key', 'label', 'slug', 'pills');

        $definitions = match ($role) {
            'admin' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'slug' => 'admin-dashboard', 'tabs' => [$t('main', 'Dashboard', 'admin-dashboard')]],
                ['key' => 'setup', 'label' => 'Academic Setup', 'icon' => 'book', 'slug' => 'academic-setup', 'tabs' => [$t('main', 'Academic Setup', 'academic-setup')]],
                ['key' => 'teachers', 'label' => 'Teachers', 'icon' => 'users', 'slug' => 'teachers', 'tabs' => [$t('main', 'Teachers', 'teacher-directory')]],
                ['key' => 'students', 'label' => 'Students', 'icon' => 'users', 'slug' => 'students', 'tabs' => [$t('main', 'Students', 'student-directory')]],
                ['key' => 'assistant', 'label' => 'AI Assistant', 'icon' => 'sparkles', 'slug' => 'assistant', 'tabs' => [$t('chat', 'Chat', 'admin-ai-assistant'), $t('actions', 'AI tools', 'ai-automation')]],
                ['key' => 'exams', 'label' => 'Exams & Date Sheets', 'icon' => 'calendar', 'slug' => 'exams', 'tabs' => [$t('overview', 'Overview', 'examination-list'), $t('datesheet', 'Date Sheet', 'date-sheet-list'), $t('admit', 'Admit Cards', 'eligible-admit-card-students')]],
                ['key' => 'seating', 'label' => 'Seating', 'icon' => 'grid', 'slug' => 'seating', 'tabs' => [$t('main', 'Seating', 'seating-plan-overview')]],
                ['key' => 'invigilation', 'label' => 'Invigilation', 'icon' => 'shield', 'slug' => 'invigilation', 'tabs' => [$t('main', 'Invigilation', 'duty-allocation-overview')]],
                ['key' => 'marks', 'label' => 'Marks & Results', 'icon' => 'award', 'slug' => 'marks', 'tabs' => [$t('submissions', 'Submissions & deadlines', 'marks-submission-overview'), $t('processing', 'Result processing', 'result-processing-overview'), $t('corrections', 'Corrections', 'result-batch-overview'), $t('rechecking', 'Rechecking', 'rechecking-request-queue')]],
                ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'chart', 'slug' => 'reports', 'tabs' => [$t('reports', 'Reports', 'reports-overview'), $t('analytics', 'Analytics & findings', 'analytics-overview')]],
                ['key' => 'rooms', 'label' => 'Rooms', 'icon' => 'home', 'slug' => 'rooms', 'tabs' => [$t('main', 'Rooms', 'room-directory')]],
                ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'slug' => 'settings', 'tabs' => [$t('ai', 'AI Settings', 'ai-settings'), $t('grading', 'Grading schemes', 'grading-scheme-list'), $t('notifications', 'Notifications', 'notification-settings'), $t('email', 'Email & recovery', 'email-settings'), $t('audit', 'Audit log', 'audit-logs'), $t('backups', 'Backups', 'backup-status'), $t('accounts', 'Accounts & permissions', 'account-and-permissions')]],
                ['key' => 'appearance', 'label' => 'Appearance & content', 'icon' => 'settings', 'slug' => 'appearance', 'tabs' => [$t('main', 'Appearance & content', 'appearance')]],
            ],
            'teacher' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'slug' => 'teacher-dashboard', 'tabs' => [$t('main', 'Dashboard', 'teacher-dashboard')]],
                ['key' => 'assistant', 'label' => 'AI Assistant', 'icon' => 'sparkles', 'slug' => 'assistant', 'tabs' => [$t('main', 'AI Assistant', 'teacher-ai-assistant')]],
                ['key' => 'courses', 'label' => 'My Courses & Marks', 'icon' => 'book', 'slug' => 'courses', 'tabs' => [$t('courses', 'Courses', 'my-courses'), $t('marks', 'Marks entry', 'marks-entry'), $t('submissions', 'Submissions', 'marks-submissions'), $t('import', 'AI import', 'marks-csv-import'), $t('students', 'Students', 'my-students')]],
                ['key' => 'schedule', 'label' => 'Exam Schedule', 'icon' => 'calendar', 'slug' => 'schedule', 'tabs' => [$t('schedule', 'Schedule', 'examination-schedule'), $t('seating', 'Seating', 'seating-plans')]],
                ['key' => 'duties', 'label' => 'Duties', 'icon' => 'shield', 'slug' => 'duties', 'tabs' => [$t('main', 'Duties', 'invigilation-duties')]],
                ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'chart', 'slug' => 'analytics', 'tabs' => [$t('main', 'Analytics', 'performance-analytics')]],
                ['key' => 'profile', 'label' => 'Profile & Availability', 'icon' => 'clock', 'slug' => 'profile', 'tabs' => [$t('main', 'Profile & availability', 'profile-and-availability')]],
            ],
            default => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'slug' => 'student-dashboard', 'tabs' => [$t('main', 'Dashboard', 'student-dashboard')]],
                ['key' => 'assistant', 'label' => 'AI Assistant', 'icon' => 'sparkles', 'slug' => 'assistant', 'tabs' => [$t('main', 'AI Assistant', 'student-ai-assistant')]],
                ['key' => 'exams', 'label' => 'Exams', 'icon' => 'calendar', 'slug' => 'exams', 'tabs' => [$t('schedule', 'Schedule', 'examination-schedule'), $t('admit', 'Admit card', 'admit-card'), $t('seat', 'My seat', 'seating-plan')]],
                ['key' => 'results', 'label' => 'Results', 'icon' => 'award', 'slug' => 'results', 'tabs' => [$t('results', 'Results', 'my-results'), $t('performance', 'Performance', 'performance-analysis')]],
                ['key' => 'rechecking', 'label' => 'Rechecking', 'icon' => 'history', 'slug' => 'rechecking', 'tabs' => [$t('main', 'Rechecking', 'rechecking-requests')]],
                ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user', 'slug' => 'profile', 'tabs' => [$t('main', 'Profile', 'my-profile')]],
            ],
        };

        foreach ($definitions as &$workspace) {
            $workspaceKey = $workspace['key'];
            $workspace['label'] = ui_text("navigation.roles.$role.groups.$workspaceKey.label", [], $workspace['label']);
            foreach ($workspace['tabs'] as &$tab) {
                $tabKey = $tab['key'];
                $tab['label'] = ui_text("navigation.roles.$role.tabs.$workspaceKey.$tabKey", [], $tab['label']);
            }
            unset($tab);
        }
        unset($workspace);

        return $definitions;
    }

    public static function workspace(string $role, string $slug): ?array
    {
        foreach (self::definition($role) as $w) {
            if ($w['slug'] === $slug && $slug !== $role.'-dashboard') {
                return $w;
            }
        }

        return null;
    }

    /** [workspaceKey, tabKey] for a legacy slug (and optional record resource), or null → dashboard. */
    public static function locate(string $role, string $slug, ?string $resource = null): array
    {
        if ($slug === 'records' && $resource) {
            return match ($resource) {
                'rooms', 'room-unavailability' => ['rooms', 'main'],
                'users' => ['settings', 'accounts'],
                'students', 'enrollments' => ['students', 'main'],
                'teacher-profiles', 'teacher-assignments' => ['teachers', 'main'],
                'sessions', 'semesters' => ['setup', 'main'],
                'departments', 'courses', 'sections' => ['setup', 'main'],
                'examinations', 'exam-courses', 'components' => ['exams', 'overview'],
                'grading', 'boundaries' => ['settings', 'grading'],
                default => ['dashboard', 'main'],
            };
        }
        $direct = ['duty-replacements' => ['invigilation', 'main'], 'my-duties' => ['duties', 'main'], 'exam-schedule' => $role === 'admin' ? ['exams', 'datesheet'] : ['schedule', 'schedule'], 'recheck-requests' => $role === 'admin' ? ['marks', 'rechecking'] : ['rechecking', 'main'], 'forecast' => $role === 'admin' ? ['reports', 'analytics'] : ($role === 'teacher' ? ['analytics', 'main'] : ['results', 'performance']), 'notification-center' => ['dashboard', 'main'], 'teacher-notifications' => ['dashboard', 'main'], 'student-notifications' => ['dashboard', 'main']];
        if (isset($direct[$slug])) {
            return $direct[$slug];
        }
        $map = self::legacy($role);

        return $map[$slug] ?? ['dashboard', 'main'];
    }

    /** Every legacy slug of the role → [workspace, tab]. */
    public static function legacy(string $role): array
    {
        static $cache = [];
        if (isset($cache[$role])) {
            return $cache[$role];
        }
        $map = [];
        $screens = (new ScreenCatalog)->forRole($role);
        if ($role === 'admin') {
            $groups = ['admin-dashboard' => ['dashboard', 'main'], 'student-directory' => ['people', 'students'], 'departments' => ['people', 'academics'], 'examination-list' => ['exams', 'overview'], 'date-sheet-list' => ['exams', 'datesheet'], 'eligible-admit-card-students' => ['exams', 'admit'], 'room-directory' => ['rooms', 'main'], 'seating-plan-overview' => ['seating', 'main'], 'duty-allocation-overview' => ['invigilation', 'main'], 'marks-submission-overview' => ['marks', 'submissions'], 'result-batch-overview' => ['marks', 'corrections'], 'grading-scheme-list' => ['settings', 'grading'], 'result-processing-overview' => ['marks', 'processing'], 'rechecking-request-queue' => ['marks', 'rechecking'], 'ai-automation' => ['assistant', 'actions'], 'analytics-overview' => ['reports', 'analytics'], 'admin-ai-assistant' => ['assistant', 'chat'], 'reports-overview' => ['reports', 'reports'], 'notification-center' => ['dashboard', 'main'], 'ai-settings' => ['settings', 'ai'], 'audit-logs' => ['settings', 'audit']];
            $over = ['teacher-directory' => ['people', 'teachers'], 'teacher-profile-availability' => ['people', 'teachers'], 'academic-sessions' => ['people', 'sessions'], 'semesters' => ['people', 'sessions'], 'credit-hour-configuration' => ['settings', 'grading'], 'assessment-components' => ['exams', 'overview'], 'administrative-activity' => ['settings', 'audit'], 'notification-settings' => ['settings', 'notifications'], 'academic-settings' => ['people', 'sessions'], 'account-and-permissions' => ['settings', 'accounts'], 'backup-status' => ['settings', 'backups'], 'bulk-generation-progress' => ['exams', 'admit'], 'gpa-and-cgpa-rules' => ['settings', 'grading'], 'ai-anomaly-review' => ['marks', 'processing']];
            $current = ['dashboard', 'main'];
            foreach ($screens as $s) {
                if ($s['kind'] === 'auth') {
                    continue;
                }
                if (isset($groups[$s['slug']])) {
                    $current = $groups[$s['slug']];
                }
                $map[$s['slug']] = $over[$s['slug']] ?? $current;
            }
            foreach ($map as $slug => $location) {
                if ($location[0] === 'people') {
                    $map[$slug] = match ($location[1]) {
                        'students' => ['students', 'main'], 'teachers' => ['teachers', 'main'], default => ['setup', 'main']
                    };
                }
            }
            $map['teacher-profile-availability'] = ['invigilation', 'main'];
            $map['email-settings'] = ['settings', 'email'];
            foreach (['password-reset-confirmation', 'unauthorized-access', 'session-expired', 'loading-records', 'empty-records', 'server-error', 'permission-denied', 'success-confirmation', 'marks-validation-error', 'no-eligible-replacement', 'missing-assessment-weights'] as $demo) {
                $map[$demo] = ['dashboard', 'main'];
            }
        } else {
            foreach (self::definition($role) as $w) {
                foreach ($w['tabs'] as $tab) {
                    $map[$tab['slug']] = [$w['key'], $tab['key']];
                }
            }
            $extra = $role === 'teacher'
                ? ['course-details' => ['courses', 'courses'], 'course-assessments' => ['courses', 'courses'], 'course-examination-schedule' => ['courses', 'courses'], 'course-performance' => ['courses', 'courses'], 'student-roster' => ['courses', 'students'], 'marks-import-errors' => ['courses', 'import'], 'returned-marks-correction' => ['courses', 'submissions'], 'marks-submission-confirmation' => ['courses', 'submissions'], 'duty-instructions' => ['duties', 'main'], 'replacement-status' => ['duties', 'main'], 'unavailability-request' => ['profile', 'main']]
                : ['updated-examination-schedule' => ['exams', 'schedule'], 'semester-results' => ['results', 'results'], 'unpublished-result' => ['results', 'results'], 'submit-rechecking-request' => ['rechecking', 'main'], 'rechecking-confirmation' => ['rechecking', 'main'], 'rechecking-decision' => ['rechecking', 'main']];
            foreach ($extra as $slug => $loc) {
                $map[$slug] = $loc;
            }
            foreach ($screens as $s) {
                $map[$s['slug']] ??= ['dashboard', 'main'];
            }
        }

        return $cache[$role] = $map;
    }

    public static function tab(string $role, string $ws, string $tab): ?array
    {
        foreach (self::definition($role) as $w) {
            if ($w['key'] === $ws) {
                foreach ($w['tabs'] as $t) {
                    if ($t['key'] === $tab) {
                        return $t;
                    }
                }
            }
        }

        return null;
    }

    /** URL of the new page that replaces a legacy slug. */
    public static function urlFor(string $role, string $slug, array $query = []): ?string
    {
        $map = self::legacy($role);
        if (! isset($map[$slug]) || in_array($slug, self::DIRECT, true)) {
            return null;
        }
        [$ws, $tab] = $map[$slug];
        if ($role === 'admin' && in_array($ws, ['setup', 'teachers', 'students'], true)) {
            $page = (new ScreenCatalog)->find($role, $slug);
            if ($page && ScreenBindings::module($page) === 'academic' && ! in_array($slug, ['teacher-directory', 'student-directory'], true)) {
                return '/admin/records?'.http_build_query(array_merge($query, ['resource' => ScreenBindings::resource($page)]));
            }
        }
        $w = collect(self::definition($role))->firstWhere('key', $ws);
        if ($slug === $w['slug']) {
            return null;
        }
        $primary = self::tab($role, $ws, $tab)['slug'] ?? null;
        $kind = (new ScreenCatalog)->find($role, $slug)['kind'] ?? null;
        $params = $query;
        if (count($w['tabs']) > 1) {
            $params['tab'] = $tab;
        }
        if ($slug !== $primary && $slug !== $w['slug'] && ! ($kind === 'state' && $role === 'admin') && $ws !== 'dashboard') {
            $params['view'] = $slug;
        }

        return '/'.$role.'/'.$w['slug'].($params ? '?'.http_build_query($params) : '');
    }
}
