<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 16)->unique();
            $table->unsignedTinyInteger('decimal_places');
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE units ADD CONSTRAINT units_decimal_places_between_zero_and_three CHECK (decimal_places BETWEEN 0 AND 3)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
