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
            $table->boolean('is_public')->nullable()->after('cost_note');
            $table->unsignedInteger('average_net_price')->nullable()->after('is_public');
            $table->string('average_net_price_year')->nullable()->after('average_net_price');
            $table->unsignedInteger('average_net_price_students')->nullable()->after('average_net_price_year');
            $table->unsignedInteger('average_net_price_high_income')->nullable()->after('average_net_price_students');
            $table->string('net_price_calculator_url')->nullable()->after('average_net_price_high_income');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colleges', function (Blueprint $table) {
            $table->dropColumn([
                'is_public',
                'average_net_price',
                'average_net_price_year',
                'average_net_price_students',
                'average_net_price_high_income',
                'net_price_calculator_url',
            ]);
        });
    }
};
