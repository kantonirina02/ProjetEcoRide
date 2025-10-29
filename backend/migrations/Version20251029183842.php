<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029183842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brands (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX uniq_brands_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE credit_ledger (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, ride_id INT DEFAULT NULL, delta INT NOT NULL, source VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F14D45DDA76ED395 (user_id), INDEX IDX_F14D45DD302A8A70 (ride_id), INDEX idx_ledger_user_date (user_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parameters (id INT AUTO_INCREMENT NOT NULL, param_key VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, UNIQUE INDEX uniq_parameters_code (param_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reviews (id INT AUTO_INCREMENT NOT NULL, ride_id INT NOT NULL, author_id INT NOT NULL, target_id INT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6970EB0FF675F31B (author_id), INDEX idx_reviews_target (target_id), INDEX idx_reviews_ride (ride_id), UNIQUE INDEX uniq_review_one_per_ride_author (ride_id, author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ride_participants (id INT AUTO_INCREMENT NOT NULL, ride_id INT NOT NULL, user_id INT NOT NULL, seats_booked INT NOT NULL, credits_used INT NOT NULL, status VARCHAR(20) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', confirmed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', cancelled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7927B92D302A8A70 (ride_id), INDEX IDX_7927B92DA76ED395 (user_id), INDEX idx_rp_status (status), UNIQUE INDEX uniq_ride_user (ride_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rides (id INT AUTO_INCREMENT NOT NULL, driver_id INT NOT NULL, vehicle_id INT NOT NULL, from_city VARCHAR(120) NOT NULL, from_lat NUMERIC(10, 7) NOT NULL, from_lng NUMERIC(10, 7) NOT NULL, to_city VARCHAR(120) NOT NULL, to_lat NUMERIC(10, 7) NOT NULL, to_lng NUMERIC(10, 7) NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', price NUMERIC(8, 2) NOT NULL, seats_total INT NOT NULL, seats_left INT NOT NULL, status VARCHAR(20) NOT NULL, allow_smoker TINYINT(1) DEFAULT 0 NOT NULL, allow_animals TINYINT(1) DEFAULT 0 NOT NULL, music_style VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9D4620A3C3423909 (driver_id), INDEX IDX_9D4620A3545317D1 (vehicle_id), INDEX idx_rides_from_to_start (from_city, to_city, start_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE roles (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, UNIQUE INDEX uniq_roles_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_roles (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_54FCD59FA76ED395 (user_id), INDEX IDX_54FCD59FD60322AC (role_id), UNIQUE INDEX uniq_user_role_pair (user_id, role_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, pseudo VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, credits_balance INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_user_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicles (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, brand_id INT NOT NULL, plate VARCHAR(20) DEFAULT NULL, model VARCHAR(100) NOT NULL, energy VARCHAR(30) NOT NULL, seats_total INT UNSIGNED NOT NULL, color VARCHAR(30) DEFAULT NULL, eco TINYINT(1) DEFAULT 0 NOT NULL, first_registration_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', INDEX IDX_1FCE69FA44F5D008 (brand_id), INDEX idx_vehicles_owner (owner_id), UNIQUE INDEX uniq_vehicles_plate (plate), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE credit_ledger ADD CONSTRAINT FK_F14D45DDA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit_ledger ADD CONSTRAINT FK_F14D45DD302A8A70 FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F302A8A70 FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F158E0B66 FOREIGN KEY (target_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE ride_participants ADD CONSTRAINT FK_7927B92D302A8A70 FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ride_participants ADD CONSTRAINT FK_7927B92DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rides ADD CONSTRAINT FK_9D4620A3C3423909 FOREIGN KEY (driver_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE rides ADD CONSTRAINT FK_9D4620A3545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_ledger DROP FOREIGN KEY FK_F14D45DDA76ED395');
        $this->addSql('ALTER TABLE credit_ledger DROP FOREIGN KEY FK_F14D45DD302A8A70');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F302A8A70');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0FF675F31B');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F158E0B66');
        $this->addSql('ALTER TABLE ride_participants DROP FOREIGN KEY FK_7927B92D302A8A70');
        $this->addSql('ALTER TABLE ride_participants DROP FOREIGN KEY FK_7927B92DA76ED395');
        $this->addSql('ALTER TABLE rides DROP FOREIGN KEY FK_9D4620A3C3423909');
        $this->addSql('ALTER TABLE rides DROP FOREIGN KEY FK_9D4620A3545317D1');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FA76ED395');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FD60322AC');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA44F5D008');
        $this->addSql('DROP TABLE brands');
        $this->addSql('DROP TABLE credit_ledger');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('DROP TABLE ride_participants');
        $this->addSql('DROP TABLE rides');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE vehicles');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
