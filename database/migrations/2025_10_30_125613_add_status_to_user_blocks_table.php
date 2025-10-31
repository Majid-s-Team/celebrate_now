<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToUserBlocksTable extends Migration
{
    public function up(): void
    {
        Schema::table('user_blocks', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('unblocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_blocks', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'blocked_at', 'unblocked_at']);
        });
    }
}
