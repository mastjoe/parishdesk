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
        Schema::create('marriage_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 30)->unique();
            $table->foreignId('groom_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('groom_name');
            $table->string('groom_parish')->nullable();
            $table->foreignId('bride_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('bride_name');
            $table->string('bride_parish')->nullable();
            $table->string('status', 20)->default('applied');
            $table->date('proposed_date')->nullable();
            $table->string('wedding_parish')->nullable();
            $table->string('officiant')->nullable();
            $table->json('checks')->nullable();
            $table->timestamp('documents_verified_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignId('matrimony_record_id')->nullable()->constrained('sacrament_records')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'proposed_date']);
        });

        Schema::create('banns_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marriage_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('published_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['marriage_application_id', 'number']);
        });

        Schema::create('banns_objections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marriage_application_id')->constrained()->cascadeOnDelete();
            $table->string('raised_by');
            $table->date('raised_on');
            $table->text('details');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banns_objections');
        Schema::dropIfExists('banns_publications');
        Schema::dropIfExists('marriage_applications');
    }
};
