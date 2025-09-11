<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'event_id')) {
                $table->foreignId('event_id')
                    ->nullable()
                    ->constrained('events')
                    ->onDelete('cascade')
                    ->after('id');
            }
        });

        // privacy column ko enum banado
        DB::statement("ALTER TABLE `posts` CHANGE `privacy` `privacy` ENUM('public','private') 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public'");
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'event_id')) {
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            }
        });

        // rollback me privacy wapas string
        DB::statement("ALTER TABLE `posts` CHANGE `privacy` `privacy` VARCHAR(255) 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    }
};
