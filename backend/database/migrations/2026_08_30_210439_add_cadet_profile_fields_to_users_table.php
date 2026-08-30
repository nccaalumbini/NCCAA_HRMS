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
        Schema::table('users', function (Blueprint $table) {
            $table->string('cadet_number', 50)->nullable()->unique()->after('phone');
            $table->foreignId('rank_id')->nullable()->after('cadet_number')->constrained('ranks')->nullOnDelete();
            $table->string('local_level')->nullable()->after('district_id');
            $table->unsignedSmallInteger('ward_number')->nullable()->after('local_level');
            $table->string('photo_path')->nullable()->after('ward_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rank_id');
            $table->dropColumn(['cadet_number', 'local_level', 'ward_number', 'photo_path']);
        });
    }
};
