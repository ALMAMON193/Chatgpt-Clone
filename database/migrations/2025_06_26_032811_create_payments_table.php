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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_subscription_id');
            $table->decimal('amount', 5, 2);
            $table->string('transaction_id', 100)->unique();
            $table->string('payment_method', 20);
            $table->dateTime('payment_date');
            $table->enum('status',  ['pending', 'successful', 'failed'])->default('pending');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->foreign('user_subscription_id')->references('id')->on('user_subscriptions')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
