ALTER TABLE `notifications`
ADD COLUMN `receiver_id` BIGINT UNSIGNED NULL AFTER `user_id`,
ADD CONSTRAINT `notifications_receiver_id_foreign`
FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
ON DELETE SET NULL;




ALTER TABLE notifications
ADD COLUMN data JSON NULL AFTER message;
