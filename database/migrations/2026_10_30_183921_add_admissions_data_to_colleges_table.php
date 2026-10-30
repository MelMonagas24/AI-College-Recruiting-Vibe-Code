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
        Schema::table('colleges', function (Blueprint $table) {
            $table->string('admissions_data_source')->nullable()->after('merit_aid_note');
            $table->string('admissions_term')->nullable()->after('admissions_data_source');
            $table->decimal('admission_rate', 4, 1)->nullable()->after('admissions_term');

            $table->string('test_policy')->nullable()->after('admission_rate');
            $table->string('test_policy_term')->nullable()->after('test_policy');
            $table->text('test_policy_note')->nullable()->after('test_policy_term');

            $table->unsignedSmallInteger('sat_composite_25th')->nullable()->after('test_policy_note');
            $table->unsignedSmallInteger('sat_composite_75th')->nullable()->after('sat_composite_25th');
            $table->decimal('sat_submitted_percent', 4, 1)->nullable()->after('sat_composite_75th');
            $table->unsignedSmallInteger('sat_reading_writing_25th')->nullable()->after('sat_submitted_percent');
            $table->unsignedSmallInteger('sat_reading_writing_75th')->nullable()->after('sat_reading_writing_25th');
            $table->unsignedSmallInteger('sat_math_25th')->nullable()->after('sat_reading_writing_75th');
            $table->unsignedSmallInteger('sat_math_75th')->nullable()->after('sat_math_25th');
            $table->unsignedTinyInteger('act_composite_25th')->nullable()->after('sat_math_75th');
            $table->unsignedTinyInteger('act_composite_75th')->nullable()->after('act_composite_25th');
            $table->decimal('act_submitted_percent', 4, 1)->nullable()->after('act_composite_75th');

            $table->decimal('average_high_school_gpa', 3, 2)->nullable()->after('act_submitted_percent');
            $table->decimal('gpa_submitted_percent', 4, 1)->nullable()->after('average_high_school_gpa');
            $table->text('gpa_note')->nullable()->after('gpa_submitted_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colleges', function (Blueprint $table) {
            $table->dropColumn([
                'admissions_data_source',
                'admissions_term',
                'admission_rate',
                'test_policy',
                'test_policy_term',
                'test_policy_note',
                'sat_composite_25th',
                'sat_composite_75th',
                'sat_submitted_percent',
                'sat_reading_writing_25th',
                'sat_reading_writing_75th',
                'sat_math_25th',
                'sat_math_75th',
                'act_composite_25th',
                'act_composite_75th',
                'act_submitted_percent',
                'average_high_school_gpa',
                'gpa_submitted_percent',
                'gpa_note',
            ]);
        });
    }
};
