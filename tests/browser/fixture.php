<?php

use App\Services\DutyService;
use App\Services\ReportService;
use App\Services\ResultsService;
use App\Services\SchedulingService;
use App\Services\SeatingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Tests\Fixture;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Browser fixtures require a dedicated MySQL database ending in _test.');
}
$harness = new class
{
    use Fixture;

    public function actingAs($user)
    {
        Auth::login($user);

        return $this;
    }

    public function run()
    {
        $f = $this->fixture();
        $f['session']->update(['starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addYear()->toDateString()]);
        $this->actingAs($f['admin']);
        $entry = app(SchedulingService::class)->schedule($f['ec'], $f['room']->id, now()->addDays(4)->setTime(9, 0)->toDateTimeString());
        app(SeatingService::class)->allocate($entry, $f['admin']->id);
        app(DutyService::class)->assign($entry, $f['teacher']->id);
        app(SchedulingService::class)->transition($f['exam'], 'approved');
        app(SchedulingService::class)->transition($f['exam'], 'published');
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $batch = app(ResultsService::class)->calculate($f['exam']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $state) {
            app(ResultsService::class)->transition($batch, $state, $f['admin']->id);
        }
        app(ReportService::class)->admit($f['exam'], $f['student']->id);
    }
};
$harness->run();
echo "Browser-only test fixtures created.\n";
