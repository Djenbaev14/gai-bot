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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_user_id')->unique();
            $table->string('phone_number')->unique();
            $table->unsignedBigInteger('region_id')->nullable()->default(8);
            $table->foreign('region_id')->references('id')->on('regions');
            $table->unsignedBigInteger('branch_id')->nullable()->default(1);
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->string('full_name')->nullable();
            $table->string('passport')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
