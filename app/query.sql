-- 11/7/2025
ALTER TABLE `users` CHANGE `name` `first_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
ALTER TABLE `users` 
ADD `last_name` VARCHAR(255) NULL AFTER `first_name`,
ADD `dob` DATE NULL AFTER `last_name`;
