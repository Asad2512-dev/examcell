<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Assign familiar local logins to the fully populated demo identities. */
class DemoLoginSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo logins are local/testing only.');
        }
        $this->call(VisualDemoSeeder::class);
        DB::transaction(function () {
            $session = AcademicSession::where('name', 'DEMO Visual Review 2026-27')->lockForUpdate()->firstOrFail();
            if (AuditLog::where('action', 'demo.logins_linked')->where('entity_id', $session->id)->exists()) {
                $this->command?->info('Requested demo logins are already linked; existing passwords retained.');

                return;
            }
            $mapping = [
                ['admin@gmail.com', 'admin@demo.examcell.test', 'admin'],
                ['teacher@gmail.com', 'cs.teacher1@demo.examcell.test', 'teacher'],
                ['student@gmail.com', 'csa.student1@demo.examcell.test', 'student'],
            ];
            $changes = [];
            foreach ($mapping as [$email, $demoEmail, $role]) {
                $demo = User::where('email', $demoEmail)->lockForUpdate()->firstOrFail();
                $existing = User::where('email', $email)->lockForUpdate()->first();
                if ($demo->role !== $role || ($existing && $existing->role !== $role)) {
                    throw new \RuntimeException('Unexpected account role for '.$email);
                }
                // Exchange login addresses, preserving every identity and related academic record.
                // The former small-demo identity remains available under the demo address.
                $temporary = 'login-transfer-'.$demo->id.'@demo.examcell.test';
                if (User::where('email', $temporary)->exists()) {
                    throw new \RuntimeException('Temporary demo address already exists.');
                }
                $demo->update(['email' => $temporary]);
                $existing?->update(['email' => $demoEmail]);
                $demo->update(['email' => $email, 'password' => '12345678', 'active' => true, 'remember_token' => null]);
                $changes[] = ['email' => $email, 'demo_user_id' => $demo->id, 'previous_user_id' => $existing?->id];
            }
            AuditLog::create(['actor_id' => User::where('email', 'admin@gmail.com')->value('id'), 'action' => 'demo.logins_linked', 'entity_type' => AcademicSession::class, 'entity_id' => $session->id, 'changes' => ['accounts' => $changes]]);
        });
        $this->command?->info('Populated demo accounts now use admin@gmail.com, teacher@gmail.com and student@gmail.com.');
    }
}
