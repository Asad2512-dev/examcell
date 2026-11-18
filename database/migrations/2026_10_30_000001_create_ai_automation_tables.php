<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature', 60)->index();
            $t->string('model', 60);
            $t->string('status', 20);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('cost_usd', 10, 6)->default(0);
            $t->timestamps();
            $t->index('created_at');
        });
        Schema::create('ai_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('feature', 60)->index();
            $t->string('status', 20)->default('queued');
            $t->unsignedTinyInteger('progress')->default(0);
            $t->string('message')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('input')->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::table('rooms', function (Blueprint $t) {
            $t->unsignedTinyInteger('seat_columns')->nullable();
            $t->json('accessible_seats')->nullable();
        });
        Schema::table('student_profiles', fn (Blueprint $t) => $t->boolean('special_needs')->default(false));
    }

    public function down(): void
    {
        Schema::table('student_profiles', fn (Blueprint $t) => $t->dropColumn('special_needs'));
        Schema::table('rooms', fn (Blueprint $t) => $t->dropColumn(['seat_columns', 'accessible_seats']));
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('ai_settings');
    }
};
