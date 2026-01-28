<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_create_video_messages_table.php

public function up(): void
{
    Schema::create('video_messages', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('thumbnail');
        $table->string('video_url');
        $table->string('duration');
        $table->integer('episode');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_messages');
    }
};
