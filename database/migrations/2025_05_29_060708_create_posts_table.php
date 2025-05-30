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
        Schema::create('posts', function (Blueprint $table) {
            $table->id(); // Alias for bigIncrements('id') - Primary Key, Auto Increment
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Foreign key to users table
            $table->string('title'); // VARCHAR(255), NOT NULL by default if not nullable()
            $table->text('content'); // TEXT, NOT NULL by default if not nullable()
            $table->timestamps(); // Adds nullable created_at and updated_at TIMESTAMPS
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
