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
            $table->string('cadet_number', 50)->nullable()->after('contact_number');
            $table->string('citizenship_number', 50)->nullable()->after('cadet_number');
            $table->string('ncc_batch', 120)->nullable()->after('citizenship_number');
            $table->string('ncc_year', 10)->nullable()->after('ncc_batch');
            $table->string('division', 120)->nullable()->after('ncc_year');
            $table->string('ncc_training_center', 150)->nullable()->after('division');
            $table->string('school', 150)->nullable()->after('ncc_training_center');
            $table->text('address')->nullable()->after('school');
            $table->string('photo_path', 255)->nullable()->after('address');
            $table->string('cv_path', 255)->nullable()->after('photo_path');
            $table->string('portfolio_path_or_url', 255)->nullable()->after('cv_path');
            $table->foreignId('skill_category_id')->nullable()->after('portfolio_path_or_url')->constrained('job_categories')->nullOnDelete();
            $table->json('skill_specific_data')->nullable()->after('skill_category_id');
            $table->boolean('applicant_confirmed_uniform_photo')->default(false)->after('skill_specific_data');
        });

        if (! Schema::hasIndex('recruitment_candidates', 'recruitment_candidates_cadet_number_unique')) {
            Schema::table('recruitment_candidates', function (Blueprint $table) {
                $table->unique('cadet_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropUnique(['cadet_number']);
            $table->dropConstrainedForeignId('skill_category_id');
            $table->dropColumn([
                'cadet_number', 'citizenship_number', 'ncc_batch', 'ncc_year', 'division',
                'ncc_training_center', 'school', 'address', 'photo_path', 'cv_path', 'portfolio_path_or_url',
                'skill_specific_data', 'applicant_confirmed_uniform_photo',
            ]);
        });
    }
};
