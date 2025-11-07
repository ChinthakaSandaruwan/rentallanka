-- Sri Lanka Rental System - MySQL Schema
    -- Generated from design: users, otp_verifications, properties, property_images, rentals, payments, settings

    -- Safety and consistency
    SET NAMES utf8mb4;
    SET time_zone = "+00:00";
    SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

    -- Create and select database
    CREATE DATABASE IF NOT EXISTS `rentallanka`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    USE `rentallanka`;

    -- Table: users
    CREATE TABLE IF NOT EXISTS `users` (
      `user_id` INT NOT NULL AUTO_INCREMENT,
      `email` VARCHAR(255) NULL,
      `nic` VARCHAR(20) NULL,
      `name` VARCHAR(100) NULL,
      `phone` VARCHAR(20) NOT NULL,
      `profile_image` VARCHAR(255) NULL,
      `role` ENUM('admin','owner','customer') NOT NULL,
      `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`),
      UNIQUE KEY `uk_users_phone` (`phone`),
      UNIQUE KEY `uk_users_email` (`email`),
      UNIQUE KEY `uk_users_name` (`name`),
      UNIQUE KEY `uk_users_nic` (`nic`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


    -- Table: packages (Advertising packages managed by Super Admin)
    CREATE TABLE IF NOT EXISTS `packages` (
      `package_id` INT NOT NULL AUTO_INCREMENT,
      `package_name` VARCHAR(150) NOT NULL,
      `package_type` ENUM('monthly','yearly','property_based','room_based') NOT NULL,
      `duration_days` INT NULL,
      `max_properties` INT NULL DEFAULT 0,
      `max_rooms` INT NULL DEFAULT 0,
      `price` DECIMAL(10,2) NOT NULL,
      `description` TEXT NULL,
      `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`package_id`),
      UNIQUE KEY `uk_packages_name` (`package_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Seed default packages (idempotent via unique key on package_name)
    INSERT INTO `packages` (`package_name`, `package_type`, `duration_days`, `max_properties`, `max_rooms`, `price`, `description`, `status`) VALUES
      ('Property Monthly 10', 'monthly', 30, 10, 0, 1999.00, 'List up to 10 properties for 30 days.', 'active'),
      ('Property Yearly 120', 'yearly', 365, 120, 0, 19999.00, 'List up to 120 properties for 1 year.', 'active'),
      ('Room Monthly 10', 'monthly', 30, 0, 10, 1499.00, 'List up to 10 rooms for 30 days.', 'active'),
      ('Room Yearly 120', 'yearly', 365, 0, 120, 14999.00, 'List up to 120 rooms for 1 year.', 'active')
    ON DUPLICATE KEY UPDATE
      `package_type`=VALUES(`package_type`),
      `duration_days`=VALUES(`duration_days`),
      `max_properties`=VALUES(`max_properties`),
      `max_rooms`=VALUES(`max_rooms`),
      `price`=VALUES(`price`),
      `description`=VALUES(`description`),
      `status`=VALUES(`status`);

    -- Table: user_packages (Owner purchases)
    CREATE TABLE IF NOT EXISTS `bought_packages` (
      `bought_package_id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `package_id` INT NOT NULL,
      `start_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `end_date` DATETIME NULL,
      `remaining_properties` INT DEFAULT 0,
      `remaining_rooms` INT DEFAULT 0,
      `status` ENUM('active','expired') NOT NULL DEFAULT 'active',
      `payment_status` ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`bought_package_id`),
      KEY `idx_bought_packages_user_id` (`user_id`),
      KEY `idx_bought_packages_package_id` (`package_id`),
      CONSTRAINT `fk_bought_packages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_bought_packages_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: package_payments (Transactions for package purchases)
    CREATE TABLE IF NOT EXISTS `package_payments` (
      `payment_id` INT NOT NULL AUTO_INCREMENT,
      `bought_package_id` INT NOT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `payment_method` ENUM('card','bank','mobile','cash') NOT NULL,
      `payment_reference` VARCHAR(255) NULL,
      `payment_status` ENUM('success','failed','pending') NOT NULL DEFAULT 'pending',
      `paid_at` DATETIME NULL,
      PRIMARY KEY (`payment_id`),
      KEY `idx_package_payments_bought_package_id` (`bought_package_id`),
      CONSTRAINT `fk_package_payments_bought_package` FOREIGN KEY (`bought_package_id`) REFERENCES `bought_packages` (`bought_package_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- sample user data 
    INSERT INTO `users` (`user_id`, `email`, `name`, `phone`, `profile_image`, `role`, `status`, `created_at`) VALUES
    (1, 'admin@rentallanka.com', 'admin', '0710476945', NULL, 'admin', 'active', '2025-11-04 12:00:00'),
    
    (2, 'owner@rentallanka.com', 'owner1', '0743282394', NULL, 'owner', 'active', '2025-11-04 12:00:00'),
    (3, 'customer@rentallanka.com', 'customer1', '0743282395', NULL, 'customer', 'active', '2025-11-04 12:00:00');

    CREATE TABLE IF NOT EXISTS `super_admins` (
      `super_admin_id` INT NOT NULL AUTO_INCREMENT,
      `email` VARCHAR(255) NOT NULL,
      `name` VARCHAR(100) NOT NULL,
      `password_hash` VARCHAR(255) NOT NULL,
      `phone` VARCHAR(20) NULL,
      `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
      `last_login_at` DATETIME NULL,
      `last_login_ip` VARCHAR(45) NULL,
      `mfa_secret` VARCHAR(255) NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`super_admin_id`),
      UNIQUE KEY `uk_super_admins_email` (`email`),
      UNIQUE KEY `uk_super_admins_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- super admin sample data password is - SuperAdmin
INSERT INTO `super_admins` (`super_admin_id`, `email`, `name`, `password_hash`, `phone`, `status`, `created_at`) VALUES
(1, 'super_admin@rentallanka.com', 'superadmin', '$2y$10$placeholderhash', '0713018095', 'active', '2025-11-04 12:00:00'); 

    -- Table: super_admin_otps (OTP codes for super admins)
    CREATE TABLE IF NOT EXISTS `super_admin_otps` (
      `sa_otp_id` INT NOT NULL AUTO_INCREMENT,
      `super_admin_id` INT NOT NULL,
      `otp_code` VARCHAR(8) NOT NULL,
      `expires_at` DATETIME NOT NULL,
      `is_verified` TINYINT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`sa_otp_id`),
      KEY `idx_sa_otp_super_admin` (`super_admin_id`),
      KEY `idx_sa_otp_code` (`otp_code`),
      CONSTRAINT `fk_sa_otp_super_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admins` (`super_admin_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: otp_verifications
    CREATE TABLE IF NOT EXISTS `user_otps` (
      `otp_id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `otp_code` VARCHAR(6) NOT NULL,
      `expires_at` DATETIME NOT NULL,
      `is_verified` BOOLEAN NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`otp_id`),
      KEY `idx_otp_user_id` (`user_id`),
      KEY `idx_otp_code` (`otp_code`),
      KEY `idx_otp_is_verified` (`is_verified`),
      CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: properties
    CREATE TABLE IF NOT EXISTS `properties` (
      `property_id` INT NOT NULL AUTO_INCREMENT,
      `property_code` VARCHAR(255) NOT NULL,
      `owner_id` INT NULL,
      `title` VARCHAR(255) NOT NULL,
      `description` TEXT NULL,
      `price_per_month` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `call_to_action` VARCHAR(255) NULL,
      `bedrooms` INT NULL DEFAULT 0,
      `bathrooms` INT NULL DEFAULT 0,
      `living_rooms` INT NULL DEFAULT 0,
      `garden` INT NULL DEFAULT 0,
      `gym` INT NULL DEFAULT 0,
      `pool` INT NULL DEFAULT 0,
      `kitchen` TINYINT NOT NULL DEFAULT 0,
      `parking` TINYINT NOT NULL DEFAULT 0,
      `water_supply` TINYINT NOT NULL DEFAULT 0,
      `electricity_supply` TINYINT NOT NULL DEFAULT 0,
      `sqft` DECIMAL(10,2) NULL,
      `property_type` ENUM('apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other') NULL,
      `image` VARCHAR(255) NULL,
      `status` ENUM('available','rented','unavailable','pending') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NULL DEFAULT NULL,
      `update_disable_at` DATETIME NULL DEFAULT NULL,
      PRIMARY KEY (`property_id`),
      KEY `idx_properties_owner_id` (`owner_id`),
      KEY `idx_properties_status` (`status`),
      CONSTRAINT `fk_properties_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


    CREATE TABLE IF NOT EXISTS `rooms` (
      `room_id` INT NOT NULL AUTO_INCREMENT,
      `room_code` VARCHAR(255) NOT NULL,
      `owner_id` INT NOT NULL,
      `title` VARCHAR(150) NOT NULL,
      `description` TEXT NULL,
      `room_type` ENUM('single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other') NULL,
      `beds` INT NOT NULL DEFAULT 1,
      `maximum_guests` INT NOT NULL DEFAULT 1,
      `price_per_day` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `status` ENUM('available','rented','unavailable','pending') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NULL DEFAULT NULL,
      `update_disable_at` DATETIME NULL DEFAULT NULL,
      PRIMARY KEY (`room_id`),
      KEY `idx_rooms_owner_id` (`owner_id`),
      KEY `idx_rooms_status` (`status`),
      CONSTRAINT `fk_rooms_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Reference geography tables

    -- locations (must be after rooms and reference tables to satisfy FKs)
    CREATE TABLE IF NOT EXISTS `locations` (
     `location_id` INT NOT NULL AUTO_INCREMENT,
     `property_id` INT NULL,
     `room_id` INT NULL,
     `province_id` INT NOT NULL,
     `district_id` INT NOT NULL,
     `city_id` INT NOT NULL,
     `address` VARCHAR(255) NULL,
     `postal_code` VARCHAR(10) NOT NULL,
     `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`location_id`),
     KEY `idx_locations_property_id` (`property_id`),
     KEY `idx_locations_room_id` (`room_id`),
     KEY `idx_locations_province_id` (`province_id`),
     KEY `idx_locations_district_id` (`district_id`),
     KEY `idx_locations_city_id` (`city_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: room_images (images attached to a specific room)
    CREATE TABLE IF NOT EXISTS `room_images` (
      `image_id` INT NOT NULL AUTO_INCREMENT,
      `room_id` INT NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `is_primary` TINYINT NOT NULL DEFAULT 0,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`image_id`),
      KEY `idx_room_images_room_id` (`room_id`),
      CONSTRAINT `fk_room_images_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: property_images (images attached to a specific property)
    CREATE TABLE IF NOT EXISTS `property_images` (
      `image_id` INT NOT NULL AUTO_INCREMENT,
      `property_id` INT NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `is_primary` TINYINT NOT NULL DEFAULT 0,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`image_id`),
      KEY `idx_property_images_property_id` (`property_id`),
      CONSTRAINT `fk_property_images_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

    -- Table: settings
    CREATE TABLE IF NOT EXISTS `settings` (
      `setting_id` INT NOT NULL AUTO_INCREMENT,
      `setting_key` VARCHAR(100) NOT NULL,
      `setting_value` TEXT NULL,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`setting_id`),
      UNIQUE KEY `uk_settings_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Seed default footer settings
    INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
      ('footer_company_name', 'Rentallanka'),
      ('footer_about', 'Find properties and rooms for rent across Sri Lanka.'),
      ('footer_address', 'Colombo, Sri Lanka'),
      ('footer_email', 'info@rentallanka.com'),
      ('footer_phone', '+94 71 234 5678'),
      ('footer_social_facebook', ''),
      ('footer_social_twitter', ''),
      ('footer_social_google', ''),
      ('footer_social_instagram', ''),
      ('footer_social_linkedin', ''),
      ('footer_social_github', ''),
      ('footer_products_links', 'Properties|/public/includes/all_properties.php\nRooms|/public/includes/all_rooms.php'),
      ('footer_useful_links', 'Pricing|#\nSettings|#\nOrders|#\nHelp|#'),
      ('footer_copyright_text', CONCAT('© ', YEAR(CURRENT_DATE), ' Copyright: ')),
      ('footer_show_social', '1'),
      ('footer_show_products', '1'),
      ('footer_show_useful_links', '1'),
      ('footer_show_contact', '1')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

    CREATE TABLE IF NOT EXISTS `reviews` (
      `review_id` INT NOT NULL AUTO_INCREMENT,
      `property_id` INT NOT NULL,
      `customer_id` INT NOT NULL,
      `rating` TINYINT NOT NULL,
      `comment` TEXT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`review_id`),
      KEY `idx_reviews_property_id` (`property_id`),
      KEY `idx_reviews_customer_id` (`customer_id`),
      CONSTRAINT `fk_reviews_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `wishlist` (
      `wishlist_id` INT NOT NULL AUTO_INCREMENT,
      `customer_id` INT NOT NULL,
      `property_id` INT NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`wishlist_id`),
      UNIQUE KEY `uk_wishlist_unique` (`customer_id`, `property_id`),
      CONSTRAINT `fk_wishlist_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_wishlist_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: admin_logs
    CREATE TABLE IF NOT EXISTS `admin_logs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `admin_id` INT NOT NULL,
      `super_admin_id` INT NULL,
      `action` VARCHAR(255) NOT NULL,
      `ip` VARCHAR(45) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ix_admin_logs_admin` (`admin_id`),
      KEY `ix_admin_logs_super_admin` (`super_admin_id`),
      CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_admin_logs_super_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admins`(`super_admin_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


    -- send sms table
    CREATE TABLE IF NOT EXISTS `sms_logs` (
      `log_id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `message` TEXT NOT NULL,
      `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
      `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`log_id`),
      KEY `idx_sms_logs_user_id` (`user_id`),
      CONSTRAINT `fk_sms_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: notifications
    CREATE TABLE IF NOT EXISTS `notifications` (
      `notification_id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `title` VARCHAR(150) NOT NULL,
      `message` TEXT NOT NULL,
      `type` ENUM('system','rental','payment','other') NOT NULL DEFAULT 'system',
      `rental_id` INT NULL,
      `property_id` INT NULL,
      `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
      `read_at` DATETIME NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`notification_id`),
      KEY `idx_notifications_user_id` (`user_id`),
      KEY `idx_notifications_is_read` (`is_read`),
      KEY `idx_notifications_rental_id` (`rental_id`),
      KEY `idx_notifications_property_id` (`property_id`),
      KEY `idx_notifications_user_read_created` (`user_id`, `is_read`, `created_at`),
      CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_notifications_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: advertiser_requests (placed after users table exists)
    DROP TABLE IF EXISTS advertiser_requests;
    CREATE TABLE IF NOT EXISTS `advertiser_requests` (
      `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `reviewed_by` INT UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`request_id`),
      KEY `idx_user_status` (`user_id`, `status`),
      CONSTRAINT `fk_advreq_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;