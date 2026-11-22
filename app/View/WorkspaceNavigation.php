<?php

namespace App\View;

use App\Support\Workspaces;

/** Presentation-only navigation. Route authorization stays in existing middleware/controllers. */
final class WorkspaceNavigation
{
    public static function groups(string $role): array
    {
        $groups = array_map(fn ($w) => ['label' => $w['label'], 'slug' => $w['slug'], 'key' => $w['key'], 'icon' => $w['icon'], 'children' => [], 'tools' => $w['tools'] ?? false], Workspaces::definition($role));
        if ($role === 'admin') {
            // Sits with the other tools: name, time zone, terms and exam times.
            array_splice($groups, count($groups) - 1, 0, [['label' => ui_text('navigation.roles.admin.groups.university.label', [], 'Your university'), 'slug' => 'university', 'key' => 'university', 'icon' => 'home', 'children' => [], 'tools' => true]]);
        }

        return $groups;
    }

    public static function active(array $group, string $slug, ?string $resource = null): bool
    {
        $ws = request()->attributes->get('ws');

        return $ws ? $ws['key'] === $group['key'] : $group['slug'] === $slug;
    }
}
