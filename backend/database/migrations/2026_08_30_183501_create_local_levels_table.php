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
        Schema::create('local_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ne')->nullable();
            $table->string('type')->default('municipality'); // municipality / rural_municipality / sub_metropolitan / metropolitan
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['district_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_levels');
    }
};
