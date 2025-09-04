<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->string('question')->nullable()->after('created_by');
            $table->boolean('allow_member_add_option')->default(false)->after('question');
            $table->boolean('allow_multiple_selection')->default(false)->after('allow_member_add_option');
        });
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropColumn(['question', 'allow_member_add_option', 'allow_multiple_selection']);
        });
    }
};
