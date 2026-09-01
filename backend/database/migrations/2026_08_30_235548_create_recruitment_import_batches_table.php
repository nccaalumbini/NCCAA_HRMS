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
        Schema::create('recruitment_import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('batch_name');
            $table->string('source_file_path')->nullable();
            $table->string('source')->default('csv');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('successful_imports')->default(0);
            $table->unsignedInteger('failed_imports')->default(0);
            $table->json('warnings')->nullable();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->string('status', 30)->default('processing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recruitment_import_batches');
    }
};
