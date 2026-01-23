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
        Schema::create('testimonies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Added Fields
            $table->string('title')->nullable();
            $table->enum('format', ['text', 'video'])->default('text');

            // Base Form Fields
            $table->string('full_name');
            $table->string('group');
            $table->string('church');

            // Content Fields
            $table->text('testimony')->nullable(); // Made nullable to support video-only format
            $table->string('video_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonies');
    }
};
