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
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->string('rejection_reason', 255)->nullable()->after('recruitment_status');
            $table->timestamp('interview_scheduled_at')->nullable()->after('rejection_reason');
            $table->string('interview_location', 255)->nullable()->after('interview_scheduled_at');
            $table->string('interview_link', 500)->nullable()->after('interview_location');
            $table->timestamp('stage_updated_at')->nullable()->after('interview_link');
            $table->foreignId('stage_updated_by')->nullable()->after('stage_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_updated_by');
            $table->dropColumn([
                'rejection_reason', 'interview_scheduled_at', 'interview_location',
                'interview_link', 'stage_updated_at',
            ]);
        });
    }
};
