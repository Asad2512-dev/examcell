<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->unique()->constrained('users')->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->boolean('reserve')->default(false);
            $t->unsignedTinyInteger('max_duties_per_day')->default(2);
            $t->timestamps();
        });
        Schema::table('examinations', function (Blueprint $t) {
            $t->foreignId('supersedes_id')->nullable()->constrained('examinations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('examinations', fn (Blueprint $t) => $t->dropConstrainedForeignId('supersedes_id'));
        Schema::dropIfExists('teacher_profiles');
    }
};
