<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
        Schema::create('admit_generation_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('queued');
            $t->unsignedInteger('generated')->default(0);
            $t->text('failure')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admit_generation_runs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
