<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    // Store user tokens for "saved cards"
    Schema::create('user_cards', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained();
        $table->string('card_token'); // ExpressPay token
        $table->string('last_four', 4);
        $table->string('card_type'); // Visa, Mastercard
        $table->timestamps();
    });

    // Track the actual transactions
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained();
        $table->decimal('amount', 15, 2);
        $table->enum('type', ['offering', 'tithe', 'partnership']);
        $table->string('status')->default('pending'); // pending, success, failed
        $table->string('transaction_reference')->unique();
        $table->timestamps();
    });
}
};
