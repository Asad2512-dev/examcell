<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $t) {
            $t->unsignedTinyInteger('seat_rows')->nullable();
            $t->json('aisles')->nullable();
            $t->json('disabled_seats')->nullable();
            $t->string('door_side', 8)->default('right');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $t) => $t->dropColumn(['seat_rows', 'aisles', 'disabled_seats', 'door_side']));
    }
};
