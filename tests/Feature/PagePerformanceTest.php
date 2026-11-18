<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_page_skips_unrelated_workflow_queries(): void
    {
        $admin = User::create([
            'name' => 'Performance Admin',
            'email' => 'performance@example.test',
            'password' => 'TestPassword2026!',
            'role' => 'admin',
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->get('/admin/rooms')->assertOk();

        $sql = implode("\n", $queries);
        foreach (['calculated_results', 'result_corrections', 'duty_requests', 'student_profiles', 'assessment_components'] as $table) {
            $this->assertStringNotContainsString('`'.$table.'`', $sql);
        }
        $this->assertLessThan(30, count($queries), 'Rooms should not load unrelated page datasets.');
    }
}
