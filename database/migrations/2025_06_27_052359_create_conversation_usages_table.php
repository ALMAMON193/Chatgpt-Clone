<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_token')->nullable();
            $table->date('date'); // Date of usage
            $table->integer('usage_minutes')->default(0); // Calculated from first_used_at to last_used_at
            $table->boolean('is_guest')->default(false);
            $table->timestamp('first_used_at')->nullable(); // ✅ First usage of the day
            $table->timestamp('last_used_at')->nullable();  // ✅ Last usage of the day
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_usages');
    }
};
