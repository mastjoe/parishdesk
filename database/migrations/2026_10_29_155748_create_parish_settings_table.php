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
        Schema::create('parish_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('patron_saint')->nullable();
            $table->string('diocese')->nullable();
            $table->string('deanery')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('theme', 30)->default('marian');
            $table->string('custom_color', 7)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('locale', 10)->default('en');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parish_settings');
    }
};
