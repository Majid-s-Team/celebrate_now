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
    Schema::table('post_reports', function (Blueprint $table) {
        $table->unique(['post_id', 'user_id']);
    });
}

public function down()
{
    Schema::table('post_reports', function (Blueprint $table) {
        $table->dropUnique(['post_reports_post_id_user_id_unique']);
    });
}

};
