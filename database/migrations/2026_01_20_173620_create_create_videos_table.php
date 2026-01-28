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
     Schema::create('videos', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('poster_path'); // This maps to "thumbnail" in Flutter
    $table->string('video_path');  // This maps to "videoUrl" in Flutter
    $table->string('duration')->default('00:00');
    $table->integer('episode')->default(1);
    $table->foreignId('user_id')->constrained();
    $table->timestamps();
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('create_videos');
    }
};
