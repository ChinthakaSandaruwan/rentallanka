-- Sri Lanka Rental System - MySQL Schema
    -- Generated from design: users, otp_verifications, properties, property_images, rentals, payments, settings

    -- Safety and consistency
    SET NAMES utf8mb4;
    SET time_zone = "+00:00";
    SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    -- Create and select database
    CREATE DATABASE IF NOT EXISTS `sri_lanka_rental_system`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    USE `sri_lanka_rental_system`;

    -- Table: users
    CREATE TABLE IF NOT EXISTS `users` (
      `user_id` INT NOT NULL AUTO_INCREMENT,
      `email` VARCHAR(255) NULL,
--  password no need (because otp login)
      `phone` VARCHAR(20) NOT NULL,
      `profile_image` VARCHAR(255) NULL,
      `role` ENUM('admin','owner','customer','super_admin') NOT NULL,
      `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`),
      UNIQUE KEY `uk_users_phone` (`phone`),
      UNIQUE KEY `uk_users_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sample user data 
INSERT INTO `users` (`user_id`, `email`, `phone`, `profile_image`, `role`, `status`, `created_at`) VALUES
(1, 'super_admin@rentallanka.com', '0713018095', NULL, 'super_admin', 'active', '2025-11-04 12:00:00'),
(2, 'admin@rentallanka.com', '0710476945', NULL, 'admin', 'active', '2025-11-04 12:00:00'),
(3, 'owner@rentallanka.com', '0743282394', NULL, 'owner', 'active', '2025-11-04 12:00:00'),
(4, 'customer@rentallanka.com', '0743282395', NULL, 'customer', 'active', '2025-11-04 12:00:00');


    -- Table: otp_verifications
    CREATE TABLE IF NOT EXISTS `otp_verifications` (
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
      `owner_id` INT NULL,
      `title` VARCHAR(255) NOT NULL,
      `description` TEXT NULL,
      `price_per_month` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `price_per_night` DECIMAL(10,2) NULL,
      `bedrooms` INT NULL DEFAULT 0,
      `bathrooms` INT NULL DEFAULT 0,
      `property_type` ENUM('house','apartment','room','commercial','other') NULL,
      `image` VARCHAR(255) NULL,
      `status` ENUM('available','rented','inactive') NOT NULL DEFAULT 'available',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`property_id`),
      KEY `idx_properties_owner_id` (`owner_id`),
      KEY `idx_properties_status` (`status`),
      CONSTRAINT `fk_properties_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `property_units` (
      `unit_id` INT NOT NULL AUTO_INCREMENT,
      `property_id` INT NOT NULL,
      `name` VARCHAR(100) NOT NULL,
      `capacity` INT NULL DEFAULT 1,
      `bedrooms` INT NULL DEFAULT 0,
      `bathrooms` INT NULL DEFAULT 0,
      `status` ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`unit_id`),
      KEY `idx_units_property_id` (`property_id`),
      CONSTRAINT `fk_units_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: unit_images (images for individual rooms/units)
    CREATE TABLE IF NOT EXISTS `unit_images` (
      `image_id` INT NOT NULL AUTO_INCREMENT,
      `unit_id` INT NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`image_id`),
      KEY `idx_unit_images_unit_id` (`unit_id`),
      CONSTRAINT `fk_unit_images_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`unit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: property_images
    CREATE TABLE IF NOT EXISTS `property_images` (
      `image_id` INT NOT NULL AUTO_INCREMENT,
      `property_id` INT NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`image_id`),
      KEY `idx_images_property_id` (`property_id`),
      CONSTRAINT `fk_images_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- location 
    CREATE TABLE IF NOT EXISTS `locations` (
     `location_id` INT NOT NULL AUTO_INCREMENT,
     `property_id` INT NOT NULL,
     `province` VARCHAR(100) NOT NULL,
     `district` VARCHAR(100) NOT NULL,
     `city` VARCHAR(100) NOT NULL,
     `address` VARCHAR(255) NULL,
     `postal_code` VARCHAR(10) NOT NULL,
     `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`location_id`),
     KEY `idx_locations_property_id` (`property_id`),
     CONSTRAINT `fk_locations_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `bookings` (
      `booking_id` INT NOT NULL AUTO_INCREMENT,
      `unit_id` INT NOT NULL,
      `customer_id` INT NOT NULL,
      `booking_type` ENUM('daily') NOT NULL,
      `start_date` DATE NOT NULL,
      `end_date` DATE NOT NULL,
      `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `with_meal` TINYINT(1) NOT NULL DEFAULT 0,
      `meal_plan` ENUM('none','breakfast','lunch','dinner','half_board','full_board','all_inclusive','custom') NOT NULL DEFAULT 'none',
      `meal_notes` VARCHAR(255) NULL,
      `status` ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`booking_id`),
      KEY `idx_bookings_unit_id` (`unit_id`),
      KEY `idx_bookings_customer_id` (`customer_id`),
      KEY `idx_bookings_status` (`status`),
      KEY `idx_bookings_start_date` (`start_date`),
      CONSTRAINT `fk_bookings_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`unit_id`) ON DELETE RESTRICT,
      CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: booking_images (images attached to a specific booking)
    CREATE TABLE IF NOT EXISTS `booking_images` (
      `image_id` INT NOT NULL AUTO_INCREMENT,
      `booking_id` INT NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`image_id`),
      KEY `idx_booking_images_booking_id` (`booking_id`),
      CONSTRAINT `fk_booking_images_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: rentals
    CREATE TABLE IF NOT EXISTS `rentals` (
      `rental_id` INT NOT NULL AUTO_INCREMENT,
      `property_id` INT NOT NULL,
      `customer_id` INT NULL,
      `start_date` DATE NOT NULL,
      `end_date` DATE NULL,
      `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `status` ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`rental_id`),
      KEY `idx_rentals_property_id` (`property_id`),
      KEY `idx_rentals_customer_id` (`customer_id`),
      KEY `idx_rentals_status` (`status`),
      CONSTRAINT `fk_rentals_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE RESTRICT,
      CONSTRAINT `fk_rentals_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: payment_slips
    CREATE TABLE IF NOT EXISTS `payment_slips` (
      `slip_id` INT NOT NULL AUTO_INCREMENT,
      `rental_id` INT NULL,
      `booking_id` INT NULL,
      `slip_path` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`slip_id`),
      KEY `idx_payment_slips_rental_id` (`rental_id`),
      KEY `idx_payment_slips_booking_id` (`booking_id`),
      CONSTRAINT `fk_payment_slips_rental` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_payment_slips_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
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
      `action` VARCHAR(255) NOT NULL,
      `ip` VARCHAR(45) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ix_admin_logs_admin` (`admin_id`),
      CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: carts (shopping cart per customer)
    CREATE TABLE IF NOT EXISTS `carts` (
      `cart_id` INT NOT NULL AUTO_INCREMENT,
      `customer_id` INT NOT NULL,
      `status` ENUM('active','checked_out','abandoned') NOT NULL DEFAULT 'active',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`cart_id`),
      KEY `idx_carts_customer_id` (`customer_id`),
      CONSTRAINT `fk_carts_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Table: cart_items (supports daily unit bookings or monthly property rentals)
    CREATE TABLE IF NOT EXISTS `cart_items` (
      `item_id` INT NOT NULL AUTO_INCREMENT,
      `cart_id` INT NOT NULL,
      `unit_id` INT NULL,
      `property_id` INT NULL,
      `item_type` ENUM('daily_unit','monthly_property') NOT NULL,
      `start_date` DATE NOT NULL,
      `end_date` DATE NOT NULL,
      `quantity` INT NOT NULL DEFAULT 1,
      `meal_plan` ENUM('none','breakfast','lunch','dinner','half_board','full_board','all_inclusive','custom') NOT NULL DEFAULT 'none',
      `price` DECIMAL(10,2) NOT NULL,
      `total_price` DECIMAL(10,2) NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`item_id`),
      KEY `idx_cart_items_cart_id` (`cart_id`),
      KEY `idx_cart_items_unit_id` (`unit_id`),
      KEY `idx_cart_items_property_id` (`property_id`),
      CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_cart_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`unit_id`) ON DELETE RESTRICT,
      CONSTRAINT `fk_cart_items_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE RESTRICT
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
      `type` ENUM('system','booking','rental','payment','other') NOT NULL DEFAULT 'system',
      `booking_id` INT NULL,
      `rental_id` INT NULL,
      `property_id` INT NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`notification_id`),
      KEY `idx_notifications_user_id` (`user_id`),
      KEY `idx_notifications_is_read` (`is_read`),
      KEY `idx_notifications_booking_id` (`booking_id`),
      KEY `idx_notifications_rental_id` (`rental_id`),
      KEY `idx_notifications_property_id` (`property_id`),
      CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_notifications_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
      CONSTRAINT `fk_notifications_rental` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE SET NULL,
      CONSTRAINT `fk_notifications_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
