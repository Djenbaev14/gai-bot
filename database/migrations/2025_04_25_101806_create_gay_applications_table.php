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
        Schema::create('gay_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->default(1);
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unsignedBigInteger('region_id')->default(8);
            $table->foreign('region_id')->references('id')->on('regions');
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('document_path')->nullable();
            $table->unsignedBigInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gay_applications');
    }
};
