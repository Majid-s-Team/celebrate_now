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
    Schema::create('events', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->date('date');
        $table->time('start_time')->nullable();
        $table->time('end_time')->nullable();
        $table->string('location')->nullable();
        $table->text('description')->nullable();
        $table->string('cover_photo_url')->nullable();
        $table->unsignedBigInteger('event_type_id')->nullable(); // FK with categories
        $table->enum('mode', ['online', 'physical']);
        $table->enum('physical_type', ['self_host', 'group_vote'])->nullable();
        $table->enum('funding_type', ['self_financed', 'donation_based'])->default('self_financed');
        $table->boolean('surprise_contribution')->default(false);
        $table->decimal('donation_goal', 12, 2)->nullable();
        $table->boolean('is_show_donation')->default(false);
        $table->date('donation_deadline')->nullable();
        $table->unsignedBigInteger('created_by'); // FK user
        $table->timestamps();
        $table->softDeletes();

        $table->foreign('event_type_id')->references('id')->on('event_categories')->onDelete('set null');
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
