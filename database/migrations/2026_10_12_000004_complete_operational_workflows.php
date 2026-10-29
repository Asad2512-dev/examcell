<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->unique(['schedule_entry_id', 'room_id']);
            $t->timestamps();
        });
        foreach (DB::table('schedule_entries')->orderBy('id')->get() as $e) {
            DB::table('schedule_rooms')->insert(['schedule_entry_id' => $e->id, 'room_id' => $e->room_id, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('seat_assignments', function (Blueprint $t) {
            $t->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $t->dropUnique(['seating_plan_id', 'seat_number']);
            $t->unique(['seating_plan_id', 'room_id', 'seat_number'], 'seats_plan_room_number_unique');
        });
        foreach (DB::table('seat_assignments')->join('seating_plans', 'seat_assignments.seating_plan_id', '=', 'seating_plans.id')->join('schedule_entries', 'seating_plans.schedule_entry_id', '=', 'schedule_entries.id')->select('seat_assignments.id', 'schedule_entries.room_id')->get() as $seat) {
            DB::table('seat_assignments')->where('id', $seat->id)->update(['room_id' => $seat->room_id]);
        }
        Schema::table('duty_assignments', fn (Blueprint $t) => $t->foreignId('room_id')->nullable()->constrained()->restrictOnDelete());
        foreach (DB::table('duty_assignments')->join('schedule_entries', 'duty_assignments.schedule_entry_id', '=', 'schedule_entries.id')->select('duty_assignments.id', 'schedule_entries.room_id')->get() as $d) {
            DB::table('duty_assignments')->where('id', $d->id)->update(['room_id' => $d->room_id]);
        }
        Schema::create('duty_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('duty_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $t->text('reason');
            $t->timestamp('requested_at');
            $t->timestamp('deadline_at');
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('decision_reason')->nullable();
            $t->timestamps();
            $t->index(['teacher_id', 'status']);
        });
        Schema::table('duty_replacements', fn (Blueprint $t) => $t->foreignId('duty_request_id')->nullable()->unique()->constrained()->restrictOnDelete());
        Schema::create('forecast_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->json('evidence');
            $t->timestamps();
        });
        Schema::create('backup_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status')->default('running');
            $t->string('path')->nullable();
            $t->string('checksum', 64)->nullable();
            $t->unsignedBigInteger('bytes')->default(0);
            $t->text('failure')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('forecast_runs');
        Schema::table('duty_replacements', fn (Blueprint $t) => $t->dropConstrainedForeignId('duty_request_id'));
        Schema::dropIfExists('duty_requests');
        Schema::table('duty_assignments', fn (Blueprint $t) => $t->dropConstrainedForeignId('room_id'));
        Schema::table('seat_assignments', function (Blueprint $t) {
            $t->dropUnique('seats_plan_room_number_unique');
            $t->dropConstrainedForeignId('room_id');
        });
        Schema::dropIfExists('schedule_rooms');
    }
};
