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
        Schema::create('calendar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('permission', ['viewer', 'editor', 'owner'])->default('viewer');
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('subscribed_at');
            $table->timestamps();
            
            $table->unique(['calendar_event_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_subscriptions');
    }
};
