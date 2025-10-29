<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029142944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brands (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX uniq_brands_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicles (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, brand_id INT NOT NULL, plate VARCHAR(20) NOT NULL, model VARCHAR(100) NOT NULL, energy VARCHAR(30) NOT NULL, seats_total INT UNSIGNED NOT NULL, color VARCHAR(30) DEFAULT NULL, eco TINYINT(1) DEFAULT 0 NOT NULL, first_registration_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', INDEX IDX_1FCE69FA44F5D008 (brand_id), INDEX idx_vehicles_owner (owner_id), UNIQUE INDEX uniq_vehicles_plate (plate), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA44F5D008');
        $this->addSql('DROP TABLE brands');
        $this->addSql('DROP TABLE vehicles');
    }
}
