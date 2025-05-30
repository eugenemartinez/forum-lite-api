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
        Schema::create('comments', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED, Primary Key, Auto Increment
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Foreign key to users table
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade'); // Foreign key to posts table
            $table->text('content'); // TEXT, NOT NULL
            $table->timestamps(); // Adds nullable created_at and updated_at TIMESTAMPS
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
