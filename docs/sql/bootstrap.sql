-- EcoRide bootstrap schema (MySQL 8)
-- Generated for local dev; align with Doctrine entities.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS brands (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    email               VARCHAR(255) NOT NULL,
    password            VARCHAR(255) NOT NULL,
    pseudo              VARCHAR(255) NOT NULL,
    phone               VARCHAR(30) DEFAULT NULL,
    credits_balance     INT NOT NULL DEFAULT 0,
    roles               JSON NOT NULL DEFAULT (JSON_ARRAY()),
    driver_preferences  JSON DEFAULT NULL,
    suspended_at        DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    suspension_reason   VARCHAR(255) DEFAULT NULL,
    profile_photo       LONGTEXT DEFAULT NULL,
    created_at          DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE KEY uniq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    owner_id               INT NOT NULL,
    brand_id               INT NOT NULL,
    plate                  VARCHAR(20) DEFAULT NULL,
    model                  VARCHAR(100) NOT NULL,
    energy                 VARCHAR(30) NOT NULL,
    seats_total            INT UNSIGNED NOT NULL,
    color                  VARCHAR(30) DEFAULT NULL,
    eco                    TINYINT(1) NOT NULL DEFAULT 0,
    first_registration_at  DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
    UNIQUE KEY uniq_vehicles_plate (plate),
    KEY idx_vehicles_owner (owner_id),
    CONSTRAINT fk_vehicle_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_vehicle_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rides (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    driver_id          INT NOT NULL,
    vehicle_id         INT NOT NULL,
    from_city          VARCHAR(120) NOT NULL,
    to_city            VARCHAR(120) NOT NULL,
    start_at           DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    end_at             DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    price              DECIMAL(8,2) NOT NULL,
    seats_total        INT NOT NULL,
    seats_left         INT NOT NULL,
    status             VARCHAR(20) NOT NULL DEFAULT 'open',
    allow_smoker       TINYINT(1) NOT NULL DEFAULT 0,
    allow_animals      TINYINT(1) NOT NULL DEFAULT 0,
    music_style        VARCHAR(50) DEFAULT NULL,
    created_at         DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at         DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    payout_released_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    KEY idx_rides_driver (driver_id),
    KEY idx_rides_vehicle (vehicle_id),
    KEY idx_rides_from_to_start (from_city, to_city, start_at),
    CONSTRAINT fk_rides_driver FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_rides_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ride_participants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ride_id         INT NOT NULL,
    user_id         INT NOT NULL,
    seats_booked    INT NOT NULL,
    credits_used    INT NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'requested',
    requested_at    DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    confirmed_at    DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    cancelled_at    DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    feedback_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    feedback_at     DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    feedback_note   LONGTEXT DEFAULT NULL,
    UNIQUE KEY uniq_ride_user (ride_id, user_id),
    KEY idx_rp_status (status),
    CONSTRAINT fk_rp_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    author_id        INT NOT NULL,
    target_id        INT NOT NULL,
    ride_id          INT DEFAULT NULL,
    rating           SMALLINT NOT NULL,
    comment          LONGTEXT DEFAULT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at       DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    validated_at     DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    validated_by_id  INT DEFAULT NULL,
    moderation_note  LONGTEXT DEFAULT NULL,
    KEY idx_reviews_author (author_id),
    KEY idx_reviews_target (target_id),
    KEY idx_reviews_ride (ride_id),
    CONSTRAINT fk_reviews_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_target FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL,
    CONSTRAINT fk_reviews_validator FOREIGN KEY (validated_by_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_ledger (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    ride_id    INT DEFAULT NULL,
    delta      INT NOT NULL,
    source     VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    KEY idx_ledger_user (user_id),
    KEY idx_ledger_ride (ride_id),
    KEY idx_ledger_user_date (user_id, created_at),
    CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ledger_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parameters (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    param_key VARCHAR(100) NOT NULL,
    value     LONGTEXT NOT NULL,
    UNIQUE KEY uniq_parameters_code (param_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT AUTO_INCREMENT NOT NULL,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_75EA56E0FB7336F0 (queue_name),
    INDEX IDX_75EA56E0E3BD61CE (available_at),
    INDEX IDX_75EA56E016BA31DB (delivered_at),
    PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default users (password hashes to replace in real env)
INSERT INTO users (email, password, pseudo, roles, credits_balance, created_at)
VALUES
  ('admin@ecoride.local', '$2y$13$examplehash', 'Admin', JSON_ARRAY('ROLE_USER','ROLE_ADMIN'), 100, NOW()),
  ('employee@ecoride.local', '$2y$13$examplehash', 'Employee', JSON_ARRAY('ROLE_USER','ROLE_EMPLOYEE'), 50, NOW())
ON DUPLICATE KEY UPDATE email = VALUES(email);
