-- 11/7/2025
ALTER TABLE `users` CHANGE `name` `first_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
ALTER TABLE `users`
ADD `last_name` VARCHAR(255) NULL AFTER `first_name`,
ADD `dob` DATE NULL AFTER `last_name`;


CREATE TABLE `post_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_media_post_id_foreign` (`post_id`),
  CONSTRAINT `post_media_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


php artisan migrate --path=database/migrations/2025_09_01_154517_create_events_table.php
php artisan migrate --path=database/migrations/2025_09_01_154520_create_event_members_table.php
php artisan migrate --path=database/migrations/2025_09_01_154521_create_polls_table.php
php artisan migrate --path=database/migrations/2025_09_01_154523_create_poll_votes_table.php
php artisan migrate --path=database/migrations/2025_09_02_114727_create_poll_candidates_tables.php
php artisan migrate --path=/database/migrations/2025_09_02_133309_create_event_donations_tables.php
php artisan migrate --path=/database/migrations/2025_09_03_124332_update_polls_table_add_custom_fields.php
php artisan migrate --path=/database/migrations/2025_09_03_130647_create_poll_options_table.php
php artisan migrate --path=/database/migrations/2025_09_03_124711_create_poll_member_options_table.php
php artisan migrate  --path=/database/migrations/2025_09_03_170759_update_posts_add_event_id_and_update_privacy_column.php
php artisan migrate  --path=/database/migrations/2025_09_08_103205_create_cards_table.php
ALTER TABLE `celebrate_now`.`posts`   
	CHANGE `privacy` `privacy` ENUM('public','private') CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'public' NULL;
