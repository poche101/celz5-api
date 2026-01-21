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
    Schema::create('payment_settings', function (Blueprint $table) {
        $table->id();
        $table->string('giving_type')->unique(); // e.g., 'tithe', 'offering'
        $table->string('account_name');
        $table->string('account_number');
        $table->string('bank_name');
        $table->string('merchant_id')->nullable(); // For ExpressPay specific accounts
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
