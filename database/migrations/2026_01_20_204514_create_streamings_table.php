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
       // Programs Table (Created by Admin)
Schema::create('programs', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('stream_link'); // YouTube/Vimeo/Mux link
    $table->dateTime('start_time');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Comments Table
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('program_id')->constrained()->onDelete('cascade');
    $table->text('message');
    $table->timestamps();
});

// Attendance Table
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('program_id')->constrained();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streamings');
    }
};
