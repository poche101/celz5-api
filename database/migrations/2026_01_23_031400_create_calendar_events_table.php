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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['meeting', 'appointment', 'reminder', 'holiday', 'event', 'task'])->default('event');
            $table->string('color')->default('#3b82f6'); // Tailwind blue-500
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('is_all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable(); // Zoom, Google Meet, etc.
            $table->string('meeting_platform')->nullable(); // zoom, google_meet, teams, etc.
            $table->string('timezone')->default('UTC');
            $table->enum('recurrence', ['none', 'daily', 'weekly', 'monthly', 'yearly'])->default('none');
            $table->json('recurrence_rules')->nullable(); // Custom recurrence rules
            $table->dateTime('recurrence_end')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->enum('visibility', ['public', 'private', 'shared'])->default('private');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->json('attendees')->nullable(); // User IDs or emails
            $table->json('reminders')->nullable(); // JSON array of reminder times
            $table->json('custom_fields')->nullable(); // For additional metadata
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['user_id', 'start_time']);
            $table->index(['start_time', 'end_time']);
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
