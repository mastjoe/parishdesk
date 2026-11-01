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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_no', 30)->unique();
            $table->string('title', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('baptismal_name', 100)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->date('wedding_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('whatsapp_phone', 30)->nullable();
            $table->string('email')->nullable()->index();
            $table->boolean('communication_consent')->default(false);
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->string('occupation', 20)->nullable();
            $table->string('state_of_origin', 100)->nullable();
            $table->string('lga_of_origin', 100)->nullable();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('family_role', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
