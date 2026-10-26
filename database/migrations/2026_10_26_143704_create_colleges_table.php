<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable columns mean "unknown": a value is only stored once it has been
     * verified from an official source.
     */
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ipeds_unit_id')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('city');
            $table->string('state', 2);

            $table->unsignedTinyInteger('ncaa_division')->nullable();
            $table->string('conference')->nullable();
            $table->boolean('has_active_womens_soccer')->default(false);
            $table->string('womens_soccer_verified_season')->nullable();

            $table->string('federal_locale')->nullable();
            $table->unsignedInteger('undergraduate_enrollment')->nullable();
            $table->string('enrollment_term')->nullable();

            $table->boolean('offers_business')->nullable();
            $table->boolean('offers_nursing')->nullable();
            $table->boolean('offers_psychology')->nullable();
            $table->boolean('offers_biology')->nullable();
            $table->boolean('offers_education')->nullable();
            $table->text('programs_note')->nullable();

            $table->string('cost_academic_year')->nullable();
            $table->unsignedInteger('tuition_fees_in_state')->nullable();
            $table->unsignedInteger('tuition_fees_out_of_state')->nullable();
            $table->unsignedInteger('cost_of_attendance_in_state')->nullable();
            $table->unsignedInteger('cost_of_attendance_out_of_state')->nullable();
            $table->boolean('cost_of_attendance_is_complete')->nullable();
            $table->text('cost_note')->nullable();

            $table->string('merit_aid')->default('unknown');
            $table->text('merit_aid_note')->nullable();

            $table->string('verification_status')->default('unverified');
            $table->date('last_verified_on')->nullable();
            $table->text('research_notes')->nullable();
            $table->timestamps();

            $table->index(['ncaa_division', 'has_active_womens_soccer']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colleges');
    }
};
