ALTER TABLE `notifications`
ADD COLUMN `receiver_id` BIGINT UNSIGNED NULL AFTER `user_id`,
ADD CONSTRAINT `notifications_receiver_id_foreign`
FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
ON DELETE SET NULL;


ALTER TABLE notifications
ADD COLUMN data JSON NULL AFTER message;

INSERT INTO `policies` (`type`, `content`, `created_at`, `updated_at`) VALUES
('terms', '<h2>Terms &amp; Conditions</h2>
<h3>Agreement Between User and (Celebrate Now)</h3>
<p>Welcome to the (Celebrate Now). (Celebrate Now) is offered to you conditioned on your acceptance without modification of the terms, conditions, and notices contained herein (the "Terms"). Your use of (Celebrate Now) constitutes your agreement to all such terms. Please read these terms carefully, and keep a copy for your reference.</p>

<h3>Privacy</h3>
<p>Your use of (Celebrate Now) is subject to (Celebrate Now) Privacy Policy. Please review our Privacy Policy, which also governs the Site and informs users of our data collection practices.</p>

<h3>Electronic Communications</h3>
<p>Visiting (Celebrate Now) or sending emails to (Celebrate Now) constitutes electronic communications. You consent to receive electronic communications...</p>

<h3>Your Account</h3>
<p>If you use this site, you are responsible for maintaining the confidentiality of your account and password...</p>

<h3>Children Under Twenty-One</h3>
<p>If you are under the age of twenty-one (21)...</p>

<h3>Links to Third-Party Sites/Services</h3>
<p>(Celebrate Now) may contain links to other websites ("Linked Sites")...</p>

<h3>No Unlawful or Prohibited Use / Intellectual Property</h3>
<p>You are granted a non-exclusive, non-transferable, revocable license to access and use (Celebrate Now)...</p>

<h3>Use of Communication Services</h3>
<p>The application may contain bulletin board services, chat areas...</p>

<h3>Materials Provided to (Celebrate Now) or Posted on Any (Celebrate Now) Website</h3>
<p>(Celebrate Now) does not claim ownership of the materials you provide...</p>

<h3>International Users</h3>
<p>The Service is controlled, operated, and administered by (Celebrate Now) from our offices within the USA...</p>

<h3>Indemnification</h3>
<p>You agree to indemnify, defend and hold harmless (Celebrate Now)...</p>

<h3>Arbitration</h3>
<p>In the event that the parties are not able to resolve any dispute...</p>

<h3>Class Action Waiver</h3>
<p>Any arbitration under these Terms and Conditions will take place individually...</p>

<h3>Liability Disclaimer</h3>
<p>The information, software, products, and services included in or available through the site may include inaccuracies...</p>

<h3>Termination / Access Restriction</h3>
<p>(Celebrate Now) reserves the right, in its sole discretion, to terminate your access...</p>

<h3>Changes to Terms</h3>
<p>(Celebrate Now) reserves the right, in its sole discretion, to change the Terms...</p>
', NOW(), NOW());


INSERT INTO `policies` (`type`, `content`, `created_at`, `updated_at`)
VALUES (
  'privacy',
  '<h2>Privacy Policy</h2>
  <p>The (Celebrate Now) values its users\' privacy. This Privacy Policy ("Policy") will help you understand how we collect and use personal information from those who visit our app or make use of our online facilities and services and what we will do with the information we collect. Our Policy has been designed and created to ensure those affiliated with (Celebrate Now) of our commitment and realization of our obligation not only to meet but exceed most existing privacy standards.</p>

  <p>We reserve the right to make changes to this Policy at any given time. If you want to make sure that you are up to date with the latest changes, we advise you to frequently visit this page. If at any point in time (Celebrate Now) decides to make use of any personally identifiable information on file in a manner vastly different from what was stated when this information was initially collected, the user or users shall be promptly notified by email. Users at that time shall have the option as to whether to permit the use of their information in this separate manner.</p>

  <p>This Policy applies to (Celebrate Now), and it governs any data collection and usage by us. Through the use of (Celebrate Now).com, you are therefore consenting to the data collection procedures expressed in this Policy.</p>

  <h3>Information We Collect</h3>
  <p>It is always up to you whether to disclose personally identifiable information to us; although you may elect not to do so, we reserve the right not to register you as a user or provide you with any products or services.</p>

  <p>In addition, (Celebrate Now) may have the occasion to collect non-personal anonymous demographic information, such as age, gender, household income, political affiliation, race, and religion, as well as the type of browser you are using, IP address, or type of operating system, which will assist us in providing and maintaining superior quality service.</p>

  <h3>Why We Collect Information and For How Long</h3>
  <p>We are collecting your data for several reasons:</p>
  <ul>
    <li>To better understand your needs and provide you with the services you have requested.</li>
    <li>To fulfill our legitimate interest in improving our services and products.</li>
    <li>To customize our app according to your personal preferences.</li>
  </ul>

  <h3>Use of Information Collected</h3>
  <p>(Celebrate Now) does not now, or will it in the future, sell, rent or lease any of its customer lists and names to any third parties.</p>

  <h3>Children Under the Age of 21</h3>
  <p>(Celebrate Now) apps are not directed to and do not knowingly collect personally identifiable information from children under the age of twenty-one (21).</p>

  <h3>Unsubscribe or Opt-out</h3>
  <p>All users and visitors to our application have the option to discontinue receiving communication from us by way of email or newsletter. To discontinue or unsubscribe from our application, please send an email that you wish to unsubscribe (App Email).</p>

  <h3>Security</h3>
  <p>(Celebrate Now) takes precautions to protect your information. When you submit sensitive information via the application, your information is protected both online and offline.</p>

  <h3>Acceptance of Terms</h3>
  <p>By using this app, you are hereby accepting the terms and conditions stipulated within the Privacy Policy Agreement.</p>',
  NOW(),
  NOW()
);


ALTER TABLE `users`
  ADD COLUMN `platform_id` VARCHAR(255) NULL AFTER `email`,
  ADD COLUMN `platform_type` ENUM('facebook','google','apple') NOT NULL AFTER `platform_id`,
  ADD COLUMN `device_type` ENUM('android','ios','web') NOT NULL AFTER `platform_type`,
  ADD COLUMN `device_token` VARCHAR(255) NULL AFTER `device_type`;

ALTER TABLE `messages`
ADD COLUMN `message_type` VARCHAR(255) NULL AFTER `message`;


// for group message working

php artisan migrate --path=/database/migrations/2025_10_17_164815_create_groups_table.php
php artisan migrate --path=/database/migrations/2025_10_17_164816_create_group_members_table.php
php artisan migrate --path=/database/migrations/2025_10_17_164817_create_group_messages_table.php
php artisan migrate --path=/database/migrations/2025_10_17_164818_create_group_message_status_table.php


ALTER TABLE `groups`
ADD COLUMN `profile_image` VARCHAR(255) NULL AFTER `name`;

ALTER TABLE group_message_status
ADD COLUMN status ENUM('sent', 'delivered', 'seen') NOT NULL DEFAULT 'sent';




ALTER TABLE group_message_status
ADD COLUMN status ENUM('sent', 'delivered', 'seen') NOT NULL DEFAULT 'sent';


	ALTER TABLE group_message_status 
	CHANGE COLUMN is_read hidden_for_receiver TINYINT(1) NOT NULL DEFAULT 0;


ALTER TABLE group_message_status
MODIFY COLUMN status ENUM('sent', 'delivered', 'read') NOT NULL DEFAULT 'sent';

UPDATE users
SET deleted_at = NULL
WHERE id = 3;

SELECT * FROM group_message_status WHERE message_id=125;


CREATE TABLE `group_memberships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `joined_at` DATETIME NOT NULL,
  `left_at` DATETIME NULL,
  `role` VARCHAR(50) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  CONSTRAINT `fk_gm_group`
    FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_gm_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,

  INDEX `idx_gm_user_group` (`user_id`, `group_id`),
  INDEX `idx_gm_group_join` (`group_id`, `joined_at`)
);



ALTER TABLE `group_members`
ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
AFTER `user_id`;




ALTER TABLE `group_members`
ADD COLUMN `current_membership_id` BIGINT UNSIGNED NULL AFTER `user_id`,
ADD CONSTRAINT `fk_current_membership`
    FOREIGN KEY (`current_membership_id`)
    REFERENCES `group_memberships` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
    
    
    
    
    CREATE TABLE report_group (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    report_reason_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NOT NULL,
    reported_by BIGINT UNSIGNED NOT NULL,

    is_active BOOLEAN DEFAULT TRUE,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    un_reported_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_report_reason
        FOREIGN KEY (report_reason_id) 
        REFERENCES report_reasons(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_report_group_group
        FOREIGN KEY (group_id) 
        REFERENCES `groups`(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_report_group_user
        FOREIGN KEY (reported_by) 
        REFERENCES users(id)
        ON DELETE CASCADE
);








