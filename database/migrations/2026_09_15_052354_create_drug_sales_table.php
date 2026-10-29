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
        Schema::create('drug_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('sales');
            $table->foreignId('drug_id')->constrained();
            $table->foreignId('drug_batch_id')->constrained();
            $table->string('quantity');
            $table->string('dosage');
            $table->string('duration')->nullable();
            $table->string('route')->nullable();
            $table->string('instruction')->nullable();
            $table->string('status')->default('active');
            $table->string('unit_price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_sales');
    }
};
