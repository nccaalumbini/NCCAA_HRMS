<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_level_id')->constrained('local_levels')->cascadeOnDelete();
            $table->unsignedSmallInteger('ward_number');
            $table->string('name_en')->nullable();
            $table->string('name_ne')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['local_level_id', 'ward_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};
