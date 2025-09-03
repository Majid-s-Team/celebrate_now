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
    Schema::create('polls', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('event_id');
        $table->unsignedBigInteger('created_by'); 
        $table->date('poll_date')->nullable(); 
        $table->enum('status', ['active', 'closed'])->default('active');
        $table->timestamps();

        $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
