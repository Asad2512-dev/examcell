<?php

namespace App\Support;

class ScreenCatalog
{
    private array $screens;

    public function __construct()
    {
        $this->screens = json_decode(file_get_contents(resource_path('screens.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function all(): array { return $this->screens; }

    public function forRole(string $role): array
    {
        return array_values(array_filter($this->screens, fn ($screen) => $screen['role'] === $role));
    }

    public function find(string $role, string $slug): ?array
    {
        foreach ($this->forRole($role) as $screen) {
            if ($screen['slug'] === $slug) return $screen;
        }
        return null;
    }

    /** Primary tasks only. Detail, edit and workflow screens stay inside their section. */
    public function navigation(string $role): array
    {
        $definitions = match ($role) {
            'teacher' => [
                ['dashboard', 'Dashboard', 'teacher-dashboard', 'dashboard', []],
                ['courses', 'My Courses', 'my-courses', 'book', [['Courses','my-courses'],['Student roster','my-students']]],
                ['marks', 'Marks', 'marks-entry', 'edit', [['Marks entry','marks-entry'],['Submissions','marks-submissions'],['Import marks','marks-csv-import']]],
                ['exams', 'Exams & Duties', 'examination-schedule', 'calendar', [['Schedule','examination-schedule'],['Seating','seating-plans'],['Invigilation duties','invigilation-duties']]],
                ['insights', 'Insights', 'performance-analytics', 'chart', [['Performance','performance-analytics'],['AI assistant','teacher-ai-assistant']]],
            ],
            'student' => [
                ['dashboard', 'Dashboard', 'student-dashboard', 'dashboard', []],
                ['courses', 'My Courses', 'my-courses', 'book', []],
                ['exams', 'Examinations', 'examination-schedule', 'calendar', [['Schedule','examination-schedule'],['Admit card','admit-card'],['Seating','seating-plan']]],
                ['results', 'My Results', 'my-results', 'award', [['Results','my-results'],['Performance','performance-analysis'],['Rechecking','rechecking-requests']]],
                ['assistant', 'AI Assistant', 'student-ai-assistant', 'sparkles', []],
            ],
            default => [
                ['dashboard', 'Dashboard', 'admin-dashboard', 'dashboard', []],
                ['academic', 'Academics', 'student-directory', 'users', [['Students','student-directory'],['Teachers','teacher-directory'],['Courses','courses'],['Enrollments','student-enrollment'],['Academic structure','departments']]],
                ['exams', 'Examinations', 'examination-list', 'calendar', [['Examinations','examination-list'],['Date sheets','date-sheet-list'],['Admit cards','eligible-admit-card-students']]],
                ['operations', 'Seating & Duties', 'seating-plan-overview', 'grid', [['Seating plans','seating-plan-overview'],['Invigilation','duty-allocation-overview']]],
                ['results', 'Marks & Results', 'marks-submission-overview', 'award', [['Marks','marks-submission-overview'],['Results','result-batch-overview'],['Rechecking','rechecking-request-queue'],['Grading','grade-boundary-editor'],['Reports','reports-overview']]],
                ['insights', 'Insights', 'analytics-overview', 'chart', [['Analytics','analytics-overview'],['AI assistant','admin-ai-assistant']]],
                ['administration', 'Administration', 'academic-settings', 'settings', [['Settings','academic-settings'],['Audit logs','audit-logs'],['Backups','backup-status']]],
            ],
        };

        return array_map(function ($definition) use ($role) {
            [$key, $label, $slug, $icon, $tools] = $definition;
            return compact('key', 'label', 'slug', 'icon') + ['tools' => array_values(array_filter(array_map(fn ($tool) => ['label' => $tool[0], 'slug' => $tool[1]], $tools), fn ($tool) => $this->find($role, $tool['slug'])))];
        }, $definitions);
    }

    public function navigationGroups(string $role): array
    {
        $items = $this->navigation($role);
        foreach ($items as &$item) {
            $item['children'] = array_values(array_filter($this->forRole($role), fn ($screen) => $screen['kind'] !== 'auth' && $this->section($screen) === $item['key']));
        }
        unset($item);
        return $items;
    }

    private function section(array $screen): string
    {
        $module = ScreenBindings::module($screen);
        if ($module === 'dashboard') return 'dashboard';
        if ($screen['role'] === 'teacher') {
            return match ($module) {
                'courses', 'roster' => 'courses',
                'marks', 'import' => 'marks',
                'schedule', 'seats', 'duties', 'admit' => 'exams',
                'analytics', 'chat', 'results', 'corrections' => 'insights',
                default => 'account',
            };
        }
        if ($screen['role'] === 'student') {
            return match ($module) {
                'courses' => 'courses',
                'schedule', 'seats', 'admit' => 'exams',
                'results', 'analytics', 'rechecks', 'corrections' => 'results',
                'chat' => 'assistant',
                default => 'account',
            };
        }
        if ($screen['slug'] === 'academic-settings') return 'administration';
        return match ($module) {
            'academic' => match (ScreenBindings::resource($screen)) {
                'examinations', 'exam-courses' => 'exams',
                'rooms', 'room-unavailability' => 'operations',
                'grading', 'boundaries', 'components' => 'results',
                default => 'academic',
            },
            'schedule', 'admit' => 'exams',
            'seats', 'duties' => 'operations',
            'marks', 'results', 'corrections', 'rechecks' => 'results',
            'analytics', 'chat' => 'insights',
            'import' => str_contains($screen['slug'], 'marks') ? 'results' : 'academic',
            'audit', 'state', 'backups' => 'administration',
            default => 'account',
        };
    }

    public function activeNavigation(string $role, array $page, ?string $resource = null): ?array
    {
        $key = $this->section($page);
        if ($role === 'admin' && $page['slug'] === 'records') {
            $key = match ($resource) {
                'examinations', 'exam-courses' => 'exams',
                'rooms', 'room-unavailability' => 'operations',
                'grading', 'boundaries', 'components' => 'results',
                default => 'academic',
            };
        }
        foreach ($this->navigationGroups($role) as $group) {
            if ($group['key'] === $key) return $group;
        }
        return null;
    }
}
