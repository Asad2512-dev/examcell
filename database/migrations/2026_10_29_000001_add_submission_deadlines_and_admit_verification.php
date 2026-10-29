<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_courses', function (Blueprint $table) {
            $table->timestamp('marks_due_at')->nullable()->index();
        });
        Schema::create('marks_submission_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('marks_due_at');
            $table->unique(['exam_course_id', 'teacher_id']);
            $table->timestamps();
        });
        Schema::table('admit_cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('verification_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('admit_cards', fn (Blueprint $table) => $table->dropColumn('verification_version'));
        Schema::dropIfExists('marks_submission_deadlines');
        Schema::table('exam_courses', fn (Blueprint $table) => $table->dropColumn('marks_due_at'));
    }
};
