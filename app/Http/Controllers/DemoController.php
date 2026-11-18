<?php

namespace App\Http\Controllers;

use App\Support\ScreenCatalog;
use Illuminate\Contracts\View\View;

class DemoController
{
    public function signIn(): View
    {
        return $this->show('admin', 'sign-in');
    }

    public function show(string $role, ?string $screen = null): View
    {
        $screen ??= $role.'-dashboard';
        $catalog = new ScreenCatalog;
        $page = $catalog->find($role, $screen);
        abort_unless($page, 404);

        return view('screens.show', [
            'page' => $page, 'role' => $role, 'navigation' => $catalog->navigation($role),
            'screens' => $catalog->forRole($role), 'allScreens' => $catalog->all(),
            'userName' => ['admin' => 'Asad Ullah', 'teacher' => 'Dr Sara Khan', 'student' => 'Ayesha Khan'][$role],
            'initials' => ['admin' => 'AU', 'teacher' => 'SK', 'student' => 'AK'][$role],
        ]);
    }
}
