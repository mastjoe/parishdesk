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
        Schema::create('sacrament_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_name');
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('received_on');
            $table->boolean('elsewhere')->default(false);
            $table->string('place')->nullable();
            $table->string('minister')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->foreignId('spouse_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('spouse_name')->nullable();
            $table->json('details')->nullable();
            $table->string('register_book', 20)->nullable();
            $table->string('register_page', 20)->nullable();
            $table->string('entry_no', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'received_on']);
            $table->index(['member_id', 'type']);
        });

        Schema::create('sacrament_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sacrament_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacrament_sponsors');
        Schema::dropIfExists('sacrament_records');
    }
};
