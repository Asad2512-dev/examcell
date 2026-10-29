<?php

use App\Models as M;
use App\Services as S;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\Fixture;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Concurrency checks require a disposable _test database.');
}
if (($argv[1] ?? '') === 'child') {
    $actor = M\User::findOrFail((int) $argv[3]);
    Auth::login($actor);
    try {
        if ($argv[2] === 'request') {
            app(S\DutyService::class)->request(M\DutyAssignment::findOrFail((int) $argv[4]), $actor->id, 'Concurrency request for reserve cover.');
        } else {
            app(S\SeatingService::class)->allocate(M\ScheduleEntry::findOrFail((int) $argv[4]), $actor->id);
        }echo 'saved';
    } catch (ValidationException $e) {
        echo 'rejected';
    }exit;
}
if (M\User::count()) {
    throw new RuntimeException('Start with an empty disposable test database.');
}
$h = new class
{
    use Fixture;

    public function seed()
    {
        return $this->fixture();
    }
};
$f = $h->seed();
$f['session']->update(['starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addYear()->toDateString()]);
Auth::login($f['admin']);
$entry = app(S\SchedulingService::class)->schedule($f['ec'], $f['room']->id, now()->addDays(4)->setTime(9, 0)->toDateTimeString());
$duty = app(S\DutyService::class)->assign($entry, $f['teacher']->id);
function parallel(string $kind, int $actor, int $id): array
{
    $processes = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $p = proc_open([PHP_BINARY, __FILE__, 'child', $kind, (string) $actor, (string) $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $processes[] = [$p, $pipes];
    }$out = [];
    foreach ($processes as [$p,$pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($p) !== 0) {
            throw new RuntimeException($err);
        }$out[] = $stdout;
    }

    return $out;
}
$requests = parallel('request', $f['teacher']->id, $duty->id);
sort($requests);
if ($requests !== ['rejected', 'saved'] || M\DutyRequest::count() !== 1) {
    throw new RuntimeException('Concurrent request uniqueness failed.');
}$seats = parallel('seats', $f['admin']->id, $entry->id);
if ($seats !== ['saved', 'saved'] || M\SeatAssignment::count() !== 2 || M\SeatingPlan::first()->revision !== 2) {
    throw new RuntimeException('Concurrent seating integrity failed.');
}
$evidence = ['concurrent_duty_requests' => 'one saved, duplicate rejected', 'concurrent_seating' => 'two serialized revisions, exactly two unique seats', 'processes_per_check' => 2];
file_put_contents(__DIR__.'/../docs/concurrency-checks.json', json_encode($evidence, JSON_PRETTY_PRINT));
echo "PASS concurrent duty request and seating transactions.\n";
