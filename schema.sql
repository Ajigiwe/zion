-- ============================================================================
-- Zion Groups of Companies - MySQL / MariaDB schema
-- Import:  mysql -u USER -p DB_NAME < schema.sql
-- phpMyAdmin: click the target database first, then Import this file.
-- (No CREATE DATABASE / USE here - shared hosts only allow your own DB.)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Child tables first so a re-import also works with FOREIGN_KEY_CHECKS = 1
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `order_events`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `promo_codes`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `product_bundles`;
DROP TABLE IF EXISTS `product_specs`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `categories`;

-- ---------------------------------------------------------------- categories
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED NULL DEFAULT NULL,
  `slug`        VARCHAR(80)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `department`  ENUM('lingerie','instruments') NOT NULL DEFAULT 'instruments',
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `image_url`   VARCHAR(500) NULL DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_dept` (`department`),
  CONSTRAINT `fk_categories_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------- brands
CREATE TABLE `brands` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80)  NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- products
CREATE TABLE `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`               VARCHAR(40)  NOT NULL,
  `slug`              VARCHAR(120) NOT NULL,
  `category_id`       INT UNSIGNED NULL DEFAULT NULL,
  `brand_id`          INT UNSIGNED NULL DEFAULT NULL,
  `department`        ENUM('lingerie','instruments') NOT NULL DEFAULT 'instruments',
  `name`              VARCHAR(160) NOT NULL,
  `brand_label`       VARCHAR(120) NULL DEFAULT NULL,
  `short_description` VARCHAR(255) NULL DEFAULT NULL,
  `description`       TEXT         NULL,
  `price`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `compare_at_price`  DECIMAL(10,2) NULL DEFAULT NULL,
  `bundle_price`      DECIMAL(10,2) NULL DEFAULT NULL,
  `stock`             INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `is_featured`       TINYINT(1)   NOT NULL DEFAULT 0,
  `badge`             VARCHAR(40)  NULL DEFAULT NULL,
  `stock_label`       VARCHAR(70)  NULL DEFAULT NULL,
  `rating`            DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  `review_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `image_url`         VARCHAR(500) NULL DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_cat` (`category_id`),
  KEY `idx_products_brand` (`brand_id`),
  KEY `idx_products_dept_active` (`department`,`is_active`),
  KEY `idx_products_featured` (`is_featured`),
  FULLTEXT KEY `ft_products_search` (`name`,`short_description`,`description`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_brand`
    FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- product_images
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `alt`        VARCHAR(255) NULL DEFAULT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pimages_product` (`product_id`),
  CONSTRAINT `fk_pimages_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- product_variants
CREATE TABLE `product_variants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `type`       ENUM('size','color') NOT NULL,
  `value`      VARCHAR(60)  NOT NULL,
  `hex`        CHAR(7)      NULL DEFAULT NULL,
  `stock`      INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pvariants_product` (`product_id`),
  KEY `idx_pvariants_type` (`product_id`,`type`),
  CONSTRAINT `fk_pvariants_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ product_specs
CREATE TABLE `product_specs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `label`      VARCHAR(80)  NOT NULL,
  `value`      VARCHAR(160) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pspecs_product` (`product_id`),
  CONSTRAINT `fk_pspecs_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- product_bundles
CREATE TABLE `product_bundles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `item_name`  VARCHAR(120) NOT NULL,
  `item_desc`  VARCHAR(160) NULL DEFAULT NULL,
  `item_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pbundles_product` (`product_id`),
  CONSTRAINT `fk_pbundles_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------- users
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `phone`         VARCHAR(30)  NULL DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `momo_verified` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- addresses
CREATE TABLE `addresses` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `recipient`  VARCHAR(120) NOT NULL,
  `phone`      VARCHAR(30)  NOT NULL,
  `region`     VARCHAR(80)  NOT NULL,
  `city`       VARCHAR(80)  NOT NULL,
  `street`     VARCHAR(255) NOT NULL,
  `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user` (`user_id`),
  CONSTRAINT `fk_addresses_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- wishlist
CREATE TABLE `wishlist` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist` (`user_id`,`product_id`),
  KEY `idx_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wishlist_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- cart_items
CREATE TABLE `cart_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_token` CHAR(40)     NOT NULL,
  `user_id`       INT UNSIGNED NULL DEFAULT NULL,
  `product_id`    INT UNSIGNED NOT NULL,
  `variant_size`  VARCHAR(60)  NULL DEFAULT NULL,
  `variant_color` VARCHAR(60)  NULL DEFAULT NULL,
  `qty`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`    DECIMAL(10,2) NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_session` (`session_token`),
  KEY `idx_cart_user` (`user_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- promo_codes
CREATE TABLE `promo_codes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `description` VARCHAR(160) NULL DEFAULT NULL,
  `type`        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at`  DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promo_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ orders
CREATE TABLE `orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`         VARCHAR(24)  NOT NULL,
  `user_id`          INT UNSIGNED NULL DEFAULT NULL,
  `customer_name`    VARCHAR(120) NOT NULL,
  `email`            VARCHAR(190) NULL DEFAULT NULL,
  `phone`            VARCHAR(30)  NOT NULL,
  `region`           VARCHAR(80)  NOT NULL,
  `city`             VARCHAR(80)  NOT NULL,
  `address`          VARCHAR(255) NOT NULL,
  `shipping_method`  ENUM('metro','regional','pickup') NOT NULL DEFAULT 'metro',
  `shipping_label`   VARCHAR(120) NOT NULL DEFAULT '',
  `shipping_fee`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_code`       VARCHAR(40)  NULL DEFAULT NULL,
  `total`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_channel`  ENUM('paystack','cod','momo','telecel','at','debit') NOT NULL DEFAULT 'paystack',
  `payment_reference` VARCHAR(60) NULL DEFAULT NULL,
  `payment_status`   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `status`           ENUM('pending','confirmed','packing','shipped','delivered','cancelled')
                     NOT NULL DEFAULT 'pending',
  `discreet_pack`    TINYINT(1) NOT NULL DEFAULT 1,
  `notes`            TEXT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_no` (`order_no`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- order_items
CREATE TABLE `order_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NULL DEFAULT NULL,
  `product_name`  VARCHAR(160) NOT NULL,
  `product_image` VARCHAR(500) NULL DEFAULT NULL,
  `variant_text`  VARCHAR(120) NULL DEFAULT NULL,
  `unit_price`    DECIMAL(10,2) NOT NULL,
  `qty`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `line_total`    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oitems_order` (`order_id`),
  KEY `idx_oitems_product` (`product_id`),
  CONSTRAINT `fk_oitems_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oitems_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ order_events
CREATE TABLE `order_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `status`     VARCHAR(40)  NOT NULL,
  `note`       VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oevents_order` (`order_id`,`created_at`),
  CONSTRAINT `fk_oevents_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- settings
CREATE TABLE `settings` (
  `skey`      VARCHAR(64)  NOT NULL,
  `svalue`    MEDIUMTEXT   NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------ contact_messages
CREATE TABLE `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `phone`      VARCHAR(40)  NULL DEFAULT NULL,
  `subject`    VARCHAR(160) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_read` (`is_read`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- reviews
-- Product reviews. products.rating / products.review_count are denormalised
-- mirrors of the approved rows here - refresh_product_rating() keeps them honest.
CREATE TABLE `reviews` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NULL DEFAULT NULL,
  `reviewer_name` VARCHAR(120) NOT NULL,
  `rating`        TINYINT UNSIGNED NOT NULL,
  `title`         VARCHAR(160) NULL DEFAULT NULL,
  `body`          TEXT NOT NULL,
  `is_approved`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_product` (`product_id`,`is_approved`,`created_at`),
  KEY `idx_reviews_pending` (`is_approved`,`created_at`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
