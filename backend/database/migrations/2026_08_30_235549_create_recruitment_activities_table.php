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
        Schema::create('recruitment_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->text('description');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            $table->index(['recruitment_candidate_id', 'performed_at'], 'recruitment_activity_candidate_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recruitment_activities');
    }
};
