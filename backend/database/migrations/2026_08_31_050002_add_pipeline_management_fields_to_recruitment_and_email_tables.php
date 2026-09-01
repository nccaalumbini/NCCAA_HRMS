<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recruitment_candidates', 'source')) {
            Schema::table('recruitment_candidates', function (Blueprint $table) {
                $table->string('source')->default('csv')->after('skills');
                $table->unsignedSmallInteger('priority_score')->default(0)->after('source');
                $table->json('tags')->nullable()->after('priority_score');
                $table->foreignId('assigned_recruiter_id')->nullable()->after('tags')->constrained('users')->nullOnDelete();
                $table->foreignId('recruitment_import_batch_id')->nullable()->after('assigned_recruiter_id')->constrained()->nullOnDelete();
                $table->timestamp('last_contacted_at')->nullable()->after('outreach_sent_at');
                $table->softDeletes();
            });
        }

        $candidateIndexes = Schema::getIndexes('recruitment_candidates');
        $hasPipelineIndex = collect($candidateIndexes)->contains(fn (array $index): bool => $index['name'] === 'recruitment_pipeline_status_recruiter_idx');

        if (! $hasPipelineIndex) {
            Schema::table('recruitment_candidates', function (Blueprint $table) {
                $table->index(['recruitment_status', 'assigned_recruiter_id'], 'recruitment_pipeline_status_recruiter_idx');
            });
        }

        if (! Schema::hasColumn('email_campaigns', 'email_template_id')) {
            Schema::table('email_campaigns', function (Blueprint $table) {
                $table->foreignId('email_template_id')->nullable()->after('reply_to')->constrained()->nullOnDelete();
                $table->timestamp('scheduled_at')->nullable()->after('queued_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_template_id');
            $table->dropColumn('scheduled_at');
        });

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropIndex(['recruitment_status', 'assigned_recruiter_id']);
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('recruitment_import_batch_id');
            $table->dropConstrainedForeignId('assigned_recruiter_id');
            $table->dropColumn(['source', 'priority_score', 'tags', 'last_contacted_at']);
        });
    }
};
