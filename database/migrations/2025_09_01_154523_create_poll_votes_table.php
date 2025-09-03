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
    Schema::create('poll_votes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('poll_id');
        $table->unsignedBigInteger('voter_id');     // kisne vote diya
        $table->unsignedBigInteger('candidate_id'); // kisko vote mila
        $table->timestamps();

        $table->foreign('poll_id')->references('id')->on('polls')->onDelete('cascade');
        $table->foreign('voter_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('candidate_id')->references('id')->on('users')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
