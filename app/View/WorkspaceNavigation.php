<?php

namespace App\View;

use App\Support\Workspaces;

/** Presentation-only navigation. Route authorization stays in existing middleware/controllers. */
final class WorkspaceNavigation
{
    public static function groups(string $role): array
    {
        return array_map(fn ($w) => ['label' => $w['label'], 'slug' => $w['slug'], 'key' => $w['key'], 'icon' => $w['icon'], 'children' => []], Workspaces::definition($role));
    }

    public static function active(array $group, string $slug, ?string $resource = null): bool
    {
        $ws = request()->attributes->get('ws');

        return $ws ? $ws['key'] === $group['key'] : $group['slug'] === $slug;
    }
}
