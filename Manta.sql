-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE `users` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,   
  `username`    VARCHAR(100)    DEFAULT NULL,
  `email`       VARCHAR(255)    DEFAULT NULL,
  `phone`       VARCHAR(20)     DEFAULT NULL,
  `password`    VARCHAR(255)    NOT NULL,
  `full_name`   VARCHAR(255)    DEFAULT NULL,
  `avatar`      VARCHAR(255)    DEFAULT NULL,
  `provider`    ENUM('local','facebook','google') DEFAULT 'local',
  `provider_id` VARCHAR(255)    DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `role`        ENUM('admin','user','seller') NOT NULL DEFAULT 'user',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tài khoản người mua';


-- ============================================================
-- 2. USER_ADDRESSES
-- ============================================================
CREATE TABLE `user_addresses` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `full_name`    VARCHAR(200)    NOT NULL,
  `phone`        VARCHAR(20)     NOT NULL,
  `province`     VARCHAR(100)    NOT NULL,
  `district`     VARCHAR(100)    NOT NULL,
  `ward`         VARCHAR(100)    NOT NULL,
  `address_line` VARCHAR(500)    NOT NULL,
  `is_default`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addr_user` (`user_id`),
  CONSTRAINT `fk_addr_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Địa chỉ giao hàng của người dùng';


-- ============================================================
-- 3. CATEGORIES
-- ============================================================
CREATE TABLE `categories` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED    DEFAULT NULL,
  `name`        VARCHAR(150)    NOT NULL,
  `slug`        VARCHAR(160)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `image_url`   VARCHAR(500)    DEFAULT NULL,
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_active` (`is_active`),
  CONSTRAINT `fk_categories_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Danh mục sản phẩm (đa cấp)';


-- ============================================================
-- 4. BRANDS
-- ============================================================
CREATE TABLE `brands` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `slug`        VARCHAR(160)    NOT NULL,
  `logo_url`    VARCHAR(500)    DEFAULT NULL,
  `description` TEXT            DEFAULT NULL,
  `country`     VARCHAR(100)    DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_slug` (`slug`),
  KEY `idx_brands_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nhãn hàng / thương hiệu';


-- ============================================================
-- 5. SELLERS
-- ============================================================
CREATE TABLE `sellers` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `shop_name`    VARCHAR(200)    NOT NULL,
  `shop_slug`    VARCHAR(210)    NOT NULL,
  `email`        VARCHAR(200)    NOT NULL,
  `phone`        VARCHAR(20)     DEFAULT NULL,
  `address`      VARCHAR(500)    DEFAULT NULL,
  `avatar_url`   VARCHAR(500)    DEFAULT NULL,
  `is_verified`  TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
   UNIQUE KEY `uq_sellers_user`   (`user_id`),
  UNIQUE KEY `uq_sellers_email`  (`email`),
  UNIQUE KEY `uq_sellers_slug`   (`shop_slug`),
  KEY `idx_sellers_active`       (`is_active`),
  KEY `idx_sellers_verified`     (`is_verified`),
CONSTRAINT `fk_sellers_user`
FOREIGN KEY (`user_id`)
REFERENCES `users` (`id`)
ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tài khoản người bán / shop';


-- ============================================================
-- 6. PRODUCTS
-- ============================================================
CREATE TABLE `products` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `seller_id`     INT UNSIGNED    NOT NULL,
  `category_id`   INT UNSIGNED    DEFAULT NULL,
  `brand_id`      INT UNSIGNED    DEFAULT NULL,
  `name`          VARCHAR(500)    NOT NULL,
  `slug`          VARCHAR(520)    NOT NULL,
  `description`   LONGTEXT        DEFAULT NULL,
  `base_price`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `status`        ENUM('draft','active','inactive','banned') NOT NULL DEFAULT 'draft',
  `is_featured`   TINYINT(1)      NOT NULL DEFAULT 0,
  `view_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `sold_count`    INT UNSIGNED    NOT NULL DEFAULT 0,   -- denorm: sync qua trigger
  `avg_rating`    DECIMAL(3,2)    NOT NULL DEFAULT 0.00, -- denorm: sync qua trigger
  `review_count`  INT UNSIGNED    NOT NULL DEFAULT 0,   -- denorm: sync qua trigger
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug`      (`slug`),
  KEY `idx_products_seller`          (`seller_id`),
  KEY `idx_products_category`        (`category_id`),
  KEY `idx_products_brand`           (`brand_id`),
  -- fix: composite thay vì 3 index đơn lẻ (price/rating/sold_count)
  KEY `idx_products_status_sold`     (`status`, `sold_count`),
  KEY `idx_products_status_rating`   (`status`, `avg_rating`),
  KEY `idx_products_status_price`    (`status`, `base_price`),
  KEY `idx_products_featured`        (`is_featured`),
  FULLTEXT KEY `ft_products_name`    (`name`),
  CONSTRAINT `fk_products_seller`
    FOREIGN KEY (`seller_id`)   REFERENCES `sellers`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_brand`
    FOREIGN KEY (`brand_id`)    REFERENCES `brands`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sản phẩm chính';


-- ============================================================
-- 7. PRODUCT_VARIANTS
-- ============================================================
CREATE TABLE `product_variants` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`       INT UNSIGNED    NOT NULL,
  `sku`              VARCHAR(100)    NOT NULL,
  `price`            DECIMAL(15,2)   NOT NULL,
  `compare_price`    DECIMAL(15,2)   DEFAULT NULL COMMENT 'Giá gạch ngang',
  `cost_price`       DECIMAL(15,2)   DEFAULT NULL COMMENT 'Giá vốn',
  `stock_quantity`   INT             NOT NULL DEFAULT 0,
  `low_stock_alert`  INT             NOT NULL DEFAULT 5,
  `size`             VARCHAR(50)     DEFAULT NULL,
  `color`            VARCHAR(50)     DEFAULT NULL,
  `material`         VARCHAR(100)    DEFAULT NULL,
  `weight`           DECIMAL(8,3)    DEFAULT NULL COMMENT 'kg',
  `barcode`          VARCHAR(100)    DEFAULT NULL,
  `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variants_sku` (`sku`),
  KEY `idx_variants_product`   (`product_id`),
  KEY `idx_variants_barcode`   (`barcode`),
  KEY `idx_variants_stock`     (`stock_quantity`),
  KEY `idx_variants_price`     (`price`),
  CONSTRAINT `fk_variants_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Biến thể sản phẩm (màu, size, chất liệu...)';


-- ============================================================
-- 8. PRODUCT_IMAGES
-- ============================================================
CREATE TABLE `product_images` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED    NOT NULL,
  `variant_id`  INT UNSIGNED    DEFAULT NULL,
  `image_url`   VARCHAR(500)    NOT NULL,
  `alt_text`    VARCHAR(300)    DEFAULT NULL,
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_primary`  TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_images_product`         (`product_id`),
  KEY `idx_images_variant`         (`variant_id`),
  KEY `idx_images_primary`         (`product_id`, `is_primary`),
  CONSTRAINT `fk_images_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_images_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hình ảnh sản phẩm';


-- ============================================================
-- 9. PRODUCT_ATTRIBUTES
-- ============================================================
CREATE TABLE `product_attributes` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED    NOT NULL,
  `attr_name`   VARCHAR(150)    NOT NULL,
  `attr_value`  VARCHAR(500)    NOT NULL,
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attrs_product` (`product_id`),
  KEY `idx_attrs_name`    (`attr_name`),
  CONSTRAINT `fk_attrs_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Thông số kỹ thuật / thuộc tính mở rộng';


-- ============================================================
-- 10. PRODUCT_TAGS
-- ============================================================
CREATE TABLE `product_tags` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED    NOT NULL,
  `tag`         VARCHAR(100)    NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_product_tag` (`product_id`, `tag`),
  KEY `idx_tags_tag` (`tag`),
  CONSTRAINT `fk_tags_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tag tìm kiếm';




















  

-- ============================================================
-- 11. INVENTORY_LOGS
-- ============================================================
CREATE TABLE `inventory_logs` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `variant_id`       INT UNSIGNED    NOT NULL,
  `action_type`      ENUM('import','export','adjust','return','damaged') NOT NULL DEFAULT 'adjust',
  `quantity_change`  INT             NOT NULL,
  `quantity_before`  INT             NOT NULL,
  `quantity_after`   INT             NOT NULL,
  `reference_id`     INT UNSIGNED    DEFAULT NULL COMMENT 'ID đơn hàng liên quan',
  `note`             VARCHAR(500)    DEFAULT NULL,
  `created_by`       INT UNSIGNED    DEFAULT NULL,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_variant` (`variant_id`),
  KEY `idx_inv_action`  (`action_type`),
  KEY `idx_inv_ref`     (`reference_id`),
  -- fix: bỏ idx_inv_created — ít dùng đơn độc, thêm lại nếu cần range query theo ngày
  CONSTRAINT `fk_inv_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lịch sử nhập / xuất kho';


-- ============================================================
-- 12. COUPONS
-- ============================================================
CREATE TABLE `coupons` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `seller_id`       INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = toàn sàn',
  `code`            VARCHAR(50)     NOT NULL,
  `name`            VARCHAR(200)    NOT NULL,
  `type`            ENUM('percent','fixed','free_ship') NOT NULL DEFAULT 'percent',
  `value`           DECIMAL(10,2)   NOT NULL,
  `min_order_value` DECIMAL(15,2)   DEFAULT NULL,
  `max_discount`    DECIMAL(15,2)   DEFAULT NULL COMMENT 'Giảm tối đa (cho type=percent)',
  `max_uses`        INT UNSIGNED    DEFAULT NULL,
  `used_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `per_user_limit`  TINYINT         NOT NULL DEFAULT 1,
  `start_date`      DATETIME        NOT NULL,
  `end_date`        DATETIME        NOT NULL,
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code`  (`code`),
  KEY `idx_coupons_seller`      (`seller_id`),
  KEY `idx_coupons_dates`       (`start_date`, `end_date`),
  KEY `idx_coupons_active`      (`is_active`),
  CONSTRAINT `fk_coupons_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mã giảm giá / voucher';


-- ============================================================
-- 13. PROMOTIONS
-- ============================================================
CREATE TABLE `promotions` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`      INT UNSIGNED    DEFAULT NULL,
  `variant_id`      INT UNSIGNED    DEFAULT NULL,
  `promo_type`      ENUM('percent','fixed','flash_sale','bundle') NOT NULL DEFAULT 'percent',
  `discount_value`  DECIMAL(10,2)   NOT NULL,
  `min_order_value` DECIMAL(15,2)   DEFAULT NULL,
  `max_uses`        INT UNSIGNED    DEFAULT NULL,
  `used_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `start_date`      DATETIME        NOT NULL,
  `end_date`        DATETIME        NOT NULL,
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- fix: đảm bảo promotion luôn gắn với ít nhất product hoặc variant
  CONSTRAINT `chk_promo_target`
    CHECK (`product_id` IS NOT NULL OR `variant_id` IS NOT NULL),
  KEY `idx_promo_product` (`product_id`),
  KEY `idx_promo_variant` (`variant_id`),
  KEY `idx_promo_dates`   (`start_date`, `end_date`),
  KEY `idx_promo_active`  (`is_active`),
  CONSTRAINT `fk_promo_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Chương trình khuyến mãi / giảm giá';


-- ============================================================
-- 14. ORDERS
-- fix: xóa tracking_number + shipping_provider (trùng với bảng shipping)
-- ============================================================
CREATE TABLE `orders` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_code`       VARCHAR(30)     NOT NULL,
  `user_id`          INT UNSIGNED    NOT NULL,
  `seller_id`        INT UNSIGNED    NOT NULL,
  `coupon_id`        INT UNSIGNED    DEFAULT NULL,
  `address_id`       INT UNSIGNED    DEFAULT NULL,
  `subtotal`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `shipping_fee`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `discount_amount`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `payment_method`   ENUM('cod','bank_transfer','momo','vnpay','zalopay','credit_card', 'bnb')
                                     NOT NULL DEFAULT 'cod',
  `payment_status`   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status`     ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned')
                                     NOT NULL DEFAULT 'pending',
  `note`             TEXT            DEFAULT NULL,
  `cancelled_reason` VARCHAR(500)    DEFAULT NULL,
  `delivered_at`     DATETIME        DEFAULT NULL,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_code`         (`order_code`),
  KEY `idx_orders_user`               (`user_id`),
  KEY `idx_orders_seller`             (`seller_id`),
  KEY `idx_orders_status`             (`order_status`),
  KEY `idx_orders_payment_status`     (`payment_status`),
  KEY `idx_orders_created`            (`created_at`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`user_id`)    REFERENCES `users`          (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_seller`
    FOREIGN KEY (`seller_id`)  REFERENCES `sellers`        (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_coupon`
    FOREIGN KEY (`coupon_id`)  REFERENCES `coupons`        (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_address`
    FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Đơn hàng';

CREATE TABLE `brand_categories` (
  `brand_id`    INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`brand_id`, `category_id`),
  FOREIGN KEY (`brand_id`)    REFERENCES `brands`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- 15. ORDER_ITEMS
-- ============================================================
CREATE TABLE `order_items` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED      NOT NULL,
  `product_id`    INT UNSIGNED      NOT NULL,
  `variant_id`    INT UNSIGNED      DEFAULT NULL,
  `product_name`  VARCHAR(500)      NOT NULL COMMENT 'Snapshot tên lúc mua',
  `sku`           VARCHAR(100)      NOT NULL COMMENT 'Snapshot SKU lúc mua',
  `color`         VARCHAR(50)       DEFAULT NULL,
  `size`          VARCHAR(50)       DEFAULT NULL,
  `quantity`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`    DECIMAL(15,2)     NOT NULL,
  `discount`      DECIMAL(15,2)     NOT NULL DEFAULT 0.00,
  `total_price`   DECIMAL(15,2)     NOT NULL,
  `created_at`    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order`   (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  KEY `idx_oi_variant` (`variant_id`),
  CONSTRAINT `fk_oi_order`
    FOREIGN KEY (`order_id`)   REFERENCES `orders`           (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_oi_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Chi tiết sản phẩm trong đơn hàng';


-- ============================================================
-- 16. WISHLIST
-- ============================================================
CREATE TABLE `wishlist` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NOT NULL,
  `product_id`  INT UNSIGNED    NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_user_product` (`user_id`, `product_id`),
  KEY `idx_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_user`
    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Danh sách yêu thích';





-- ============================================================
-- 18. SHIPPING
-- ============================================================
CREATE TABLE `shipping` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_id`        INT UNSIGNED    NOT NULL,
  `provider`        VARCHAR(100)    NOT NULL COMMENT 'GHN, GHTK, VNPost...',
  `tracking_number` VARCHAR(100)    NOT NULL,
  `status`          ENUM('waiting_pickup','picked_up','in_transit','out_for_delivery','delivered','failed','returned')
                                    NOT NULL DEFAULT 'waiting_pickup',
  `estimated_date`  DATE            DEFAULT NULL,
  `delivered_at`    DATETIME        DEFAULT NULL,
  `fee`             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `weight`          DECIMAL(8,3)    DEFAULT NULL COMMENT 'kg',
  `note`            VARCHAR(500)    DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipping_tracking` (`tracking_number`),
  KEY `idx_shipping_order`  (`order_id`),
  KEY `idx_shipping_status` (`status`),
  CONSTRAINT `fk_shipping_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Thông tin vận chuyển đơn hàng';

-- ============================================================
-- 17. REVIEWS
-- ============================================================
CREATE TABLE `reviews` (
  `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`           INT UNSIGNED    NOT NULL,
  `variant_id`           INT UNSIGNED    DEFAULT NULL,
  `user_id`              INT UNSIGNED    NOT NULL,
  `order_item_id`        INT UNSIGNED    DEFAULT NULL,
  `rating`               TINYINT         NOT NULL,
  `title`                VARCHAR(300)    DEFAULT NULL,
  `content`              TEXT            DEFAULT NULL,
  `image_urls`           JSON            DEFAULT NULL,
  `is_verified_purchase` TINYINT(1)      NOT NULL DEFAULT 0,
  `is_approved`          TINYINT(1)      NOT NULL DEFAULT 0,
  `helpful_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `reply`                TEXT            DEFAULT NULL COMMENT 'Phản hồi từ seller',
  `replied_at`           DATETIME        DEFAULT NULL,
  `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5),
  KEY `idx_reviews_product`  (`product_id`),
  KEY `idx_reviews_user`     (`user_id`),
  KEY `idx_reviews_rating`   (`rating`),
  KEY `idx_reviews_approved` (`is_approved`),
  CONSTRAINT `fk_reviews_product`
    FOREIGN KEY (`product_id`)    REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_variant`
    FOREIGN KEY (`variant_id`)    REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_user`
    FOREIGN KEY (`user_id`)       REFERENCES `users`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_oi`
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`      (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Đánh giá sản phẩm từ người mua';

-- ============================================================
-- 19. SHIPPING_LOGS
-- ============================================================
CREATE TABLE `shipping_logs` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `shipping_id` INT UNSIGNED    NOT NULL,
  `status`      VARCHAR(100)    NOT NULL,
  `location`    VARCHAR(300)    DEFAULT NULL,
  `description` VARCHAR(500)    DEFAULT NULL,
  `logged_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shlog_shipping` (`shipping_id`),
  CONSTRAINT `fk_shlog_shipping`
    FOREIGN KEY (`shipping_id`) REFERENCES `shipping` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lịch sử trạng thái vận chuyển';


-- ============================================================
-- TRIGGERS — sync denormalized counters trên products
-- ============================================================
DELIMITER $$

-- Sync avg_rating + review_count sau khi INSERT review được duyệt
CREATE TRIGGER `trg_review_after_insert`
AFTER INSERT ON `reviews`
FOR EACH ROW
BEGIN
  IF NEW.is_approved = 1 THEN
    UPDATE products
    SET
      avg_rating   = (SELECT ROUND(AVG(rating), 2) FROM reviews WHERE product_id = NEW.product_id AND is_approved = 1),
      review_count = (SELECT COUNT(*)               FROM reviews WHERE product_id = NEW.product_id AND is_approved = 1)
    WHERE id = NEW.product_id;
  END IF;
END$$

-- Sync khi review bị xóa
CREATE TRIGGER `trg_review_after_delete`
AFTER DELETE ON `reviews`
FOR EACH ROW
BEGIN
  IF OLD.is_approved = 1 THEN
    UPDATE products
    SET
      avg_rating   = COALESCE((SELECT ROUND(AVG(rating), 2) FROM reviews WHERE product_id = OLD.product_id AND is_approved = 1), 0),
      review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = OLD.product_id AND is_approved = 1)
    WHERE id = OLD.product_id;
  END IF;
END$$

-- Sync khi review được approve/unapprove (UPDATE is_approved)
CREATE TRIGGER `trg_review_after_update`
AFTER UPDATE ON `reviews`
FOR EACH ROW
BEGIN
  IF OLD.is_approved <> NEW.is_approved OR OLD.rating <> NEW.rating THEN
    UPDATE products
    SET
      avg_rating   = COALESCE((SELECT ROUND(AVG(rating), 2) FROM reviews WHERE product_id = NEW.product_id AND is_approved = 1), 0),
      review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = NEW.product_id AND is_approved = 1)
    WHERE id = NEW.product_id;
  END IF;
END$$

-- Sync sold_count sau khi đơn hàng delivered
-- Kích hoạt khi order_status chuyển sang 'delivered'
CREATE TRIGGER `trg_order_status_after_update`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
  IF OLD.order_status <> 'delivered' AND NEW.order_status = 'delivered' THEN
    UPDATE products p
    JOIN order_items oi ON oi.product_id = p.id AND oi.order_id = NEW.id
    SET p.sold_count = p.sold_count + oi.quantity;
  END IF;
END$$

DELIMITER ;


-- ============================================================
-- VIEWS
-- ============================================================

CREATE OR REPLACE VIEW `v_products_overview` AS
SELECT
  p.id,
  p.name                                AS product_name,
  p.status,
  p.base_price,
  p.avg_rating,
  p.review_count,
  p.sold_count,
  p.view_count,
  p.is_featured,
  c.name                                AS category_name,
  b.name                                AS brand_name,
  s.shop_name                           AS seller_name,
  COUNT(DISTINCT pv.id)                 AS variant_count,
  COALESCE(SUM(pv.stock_quantity), 0)   AS total_stock
FROM products p
LEFT JOIN categories       c  ON c.id = p.category_id
LEFT JOIN brands           b  ON b.id = p.brand_id
LEFT JOIN sellers          s  ON s.id = p.seller_id
LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
GROUP BY p.id, p.name, p.status, p.base_price, p.avg_rating,
         p.review_count, p.sold_count, p.view_count, p.is_featured,
         c.name, b.name, s.shop_name;


CREATE OR REPLACE VIEW `v_low_stock_variants` AS
SELECT
  pv.id             AS variant_id,
  pv.sku,
  p.name            AS product_name,
  pv.color,
  pv.size,
  pv.stock_quantity,
  pv.low_stock_alert,
  s.shop_name       AS seller_name
FROM product_variants pv
JOIN products p ON p.id = pv.product_id
JOIN sellers  s ON s.id = p.seller_id
WHERE pv.stock_quantity <= pv.low_stock_alert
  AND pv.is_active = 1;


CREATE OR REPLACE VIEW `v_active_promotions` AS
SELECT
  pr.id,
  p.name            AS product_name,
  pv.sku,
  pr.promo_type,
  pr.discount_value,
  pr.start_date,
  pr.end_date
FROM promotions pr
JOIN products          p  ON p.id  = pr.product_id
LEFT JOIN product_variants pv ON pv.id = pr.variant_id
WHERE pr.is_active = 1
  AND NOW() BETWEEN pr.start_date AND pr.end_date;


-- fix: thêm shipping info, bỏ tracking_number khỏi orders (đã chuyển hết sang shipping)
CREATE OR REPLACE VIEW `v_order_summary` AS
SELECT
  o.id                                  AS order_id,
  o.order_code,
  o.order_status,
  o.payment_method,
  o.payment_status,
  o.total_amount,
  o.created_at,
  u.full_name                           AS buyer_name,
  u.email                               AS buyer_email,
  s.shop_name                           AS seller_name,
  COUNT(oi.id)                          AS item_count,
  sh.provider                           AS shipping_provider,
  sh.tracking_number,
  sh.status                             AS shipping_status
FROM orders o
JOIN users       u  ON u.id  = o.user_id
JOIN sellers     s  ON s.id  = o.seller_id
JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN shipping sh ON sh.order_id = o.id
GROUP BY o.id, o.order_code, o.order_status, o.payment_method,
         o.payment_status, o.total_amount, o.created_at,
         u.full_name, u.email, s.shop_name,
         sh.provider, sh.tracking_number, sh.status;


CREATE OR REPLACE VIEW `v_revenue_by_seller` AS
SELECT
  s.id                                  AS seller_id,
  s.shop_name,
  COUNT(DISTINCT o.id)                  AS total_orders,
  SUM(o.total_amount)                   AS total_revenue,
  AVG(o.total_amount)                   AS avg_order_value,
  SUM(oi.quantity)                      AS total_items_sold
FROM sellers     s
JOIN orders      o  ON o.seller_id = s.id AND o.order_status = 'delivered'
JOIN order_items oi ON oi.order_id  = o.id
GROUP BY s.id, s.shop_name;


-- fix: bỏ ORDER BY trong view (MySQL bỏ qua khi wrap)
-- caller tự ORDER BY total_qty_sold DESC khi cần
CREATE OR REPLACE VIEW `v_top_selling_products` AS
SELECT
  p.id,
  p.name              AS product_name,
  p.sold_count,
  p.avg_rating,
  p.base_price,
  c.name              AS category_name,
  b.name              AS brand_name,
  SUM(oi.quantity)    AS total_qty_sold,
  SUM(oi.total_price) AS total_revenue
FROM products    p
JOIN order_items oi ON oi.product_id = p.id
JOIN orders      o  ON o.id = oi.order_id AND o.order_status = 'delivered'
LEFT JOIN categories c ON c.id = p.category_id
LEFT JOIN brands     b ON b.id = p.brand_id
GROUP BY p.id, p.name, p.sold_count, p.avg_rating, p.base_price, c.name, b.name;


-- ============================================================
-- STORED PROCEDURES
-- ============================================================
DELIMITER $$

-- Lấy sản phẩm theo danh mục (bao gồm cả danh mục con)
CREATE PROCEDURE `sp_get_products_by_category` (
  IN p_category_id INT UNSIGNED,
  IN p_limit       INT UNSIGNED,
  IN p_offset      INT UNSIGNED
)
BEGIN
  SELECT p.*, c.name AS category_name, b.name AS brand_name, s.shop_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN brands     b ON b.id = p.brand_id
  LEFT JOIN sellers    s ON s.id = p.seller_id
  WHERE p.status = 'active'
    AND (
      p.category_id = p_category_id
      OR p.category_id IN (
        SELECT id FROM categories WHERE parent_id = p_category_id
      )
    )
  ORDER BY p.sold_count DESC
  LIMIT p_limit OFFSET p_offset;
END$$


-- Cập nhật tồn kho sau khi đặt hàng
CREATE PROCEDURE `sp_reduce_stock` (
  IN p_variant_id INT UNSIGNED,
  IN p_quantity   INT UNSIGNED,
  IN p_order_id   INT UNSIGNED
)
BEGIN
  DECLARE v_current_stock INT;

  SELECT stock_quantity INTO v_current_stock
  FROM product_variants WHERE id = p_variant_id FOR UPDATE;

  IF v_current_stock < p_quantity THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Không đủ tồn kho';
  ELSE
    UPDATE product_variants
    SET stock_quantity = stock_quantity - p_quantity
    WHERE id = p_variant_id;

    INSERT INTO inventory_logs
      (variant_id, action_type, quantity_change, quantity_before, quantity_after, reference_id, note)
    VALUES
      (p_variant_id, 'export', -p_quantity, v_current_stock,
       v_current_stock - p_quantity, p_order_id, CONCAT('Đơn hàng #', p_order_id));
  END IF;
END$$


-- Tính doanh thu theo khoảng thời gian
CREATE PROCEDURE `sp_revenue_report` (
  IN p_from_date DATE,
  IN p_to_date   DATE,
  IN p_seller_id INT UNSIGNED
)
BEGIN
  SELECT
    DATE(o.created_at)        AS date,
    COUNT(DISTINCT o.id)      AS order_count,
    SUM(o.total_amount)       AS revenue,
    SUM(o.discount_amount)    AS total_discount,
    AVG(o.total_amount)       AS avg_order_value
  FROM orders o
  WHERE DATE(o.created_at) BETWEEN p_from_date AND p_to_date
    AND o.order_status = 'delivered'
    AND (p_seller_id IS NULL OR o.seller_id = p_seller_id)
  GROUP BY DATE(o.created_at)
  ORDER BY date;
END$$


-- Tìm kiếm sản phẩm — dùng tham số trực tiếp thay vì nối chuỗi SQL
-- fix: loại bỏ dynamic SQL nối chuỗi để tránh SQL injection
-- Nếu cần linh hoạt hơn, nên xử lý filter logic ở tầng ứng dụng
CREATE PROCEDURE `sp_search_products` (
  IN p_keyword     VARCHAR(200),
  IN p_min_price   DECIMAL(15,2),
  IN p_max_price   DECIMAL(15,2),
  IN p_category_id INT UNSIGNED,
  IN p_brand_id    INT UNSIGNED,
  IN p_sort        VARCHAR(30),
  IN p_limit       INT UNSIGNED,
  IN p_offset      INT UNSIGNED
)
BEGIN
  SELECT
    p.id,
    p.name,
    p.base_price,
    p.avg_rating,
    p.sold_count,
    b.name AS brand_name,
    c.name AS category_name
  FROM products p
  LEFT JOIN brands     b ON b.id = p.brand_id
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.status = 'active'
    AND (p_keyword     IS NULL OR MATCH(p.name) AGAINST(p_keyword IN BOOLEAN MODE))
    AND (p_min_price   IS NULL OR p.base_price >= p_min_price)
    AND (p_max_price   IS NULL OR p.base_price <= p_max_price)
    AND (p_category_id IS NULL OR p.category_id = p_category_id)
    AND (p_brand_id    IS NULL OR p.brand_id    = p_brand_id)
  ORDER BY
    CASE p_sort
      WHEN 'price_asc'   THEN p.base_price  END ASC,
    CASE p_sort
      WHEN 'price_desc'  THEN p.base_price  END DESC,
    CASE p_sort
      WHEN 'best_seller' THEN p.sold_count  END DESC,
    CASE p_sort
      WHEN 'top_rated'   THEN p.avg_rating  END DESC,
    CASE WHEN p_sort NOT IN ('price_asc','price_desc','best_seller','top_rated')
      THEN p.created_at END DESC
  LIMIT p_limit OFFSET p_offset;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;



CREATE TABLE `cart` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user` (`user_id`),
  CONSTRAINT `fk_cart_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Giỏ hàng của người dùng';

CREATE TABLE `cart_items` (
  `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `cart_id`      INT UNSIGNED      NOT NULL,
  `product_id`   INT UNSIGNED      NOT NULL,
  `variant_id`   INT UNSIGNED      DEFAULT NULL,
  `quantity`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_item` (`cart_id`, `variant_id`),
  KEY `idx_ci_cart`    (`cart_id`),
  KEY `idx_ci_product` (`product_id`),
  CONSTRAINT `fk_ci_cart`
    FOREIGN KEY (`cart_id`)    REFERENCES `cart`             (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sản phẩm trong giỏ hàng';



CREATE TABLE `nft_orders` (
  `id` int(11) NOT NULL,
  `wallet` varchar(100) DEFAULT NULL,
  `tx_hash` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `status` enum('pending','minted','failed') DEFAULT 'pending',
  `token_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `nft_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tx_hash` (`tx_hash`);
ALTER TABLE `nft_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;
COMMIT;
--

CREATE TABLE cancel_requests (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    reason   TEXT,
    status   ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_note TEXT,
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    CONSTRAINT fk_cr_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders 
ADD COLUMN buyer_wallet VARCHAR(100) DEFAULT NULL,
ADD COLUMN refund_tx_hash VARCHAR(100) DEFAULT NULL,
ADD COLUMN refund_bnb_amount DECIMAL(18,8) DEFAULT NULL;


