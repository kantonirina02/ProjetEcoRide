<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251102145550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE reviews ADD validated_by_id INT DEFAULT NULL, ADD status VARCHAR(20) NOT NULL DEFAULT 'pending', ADD validated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD moderation_note LONGTEXT DEFAULT NULL");
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FC69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6970EB0FC69DE5E5 ON reviews (validated_by_id)');
        $this->addSql("ALTER TABLE users ADD driver_preferences JSON DEFAULT NULL, ADD suspended_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD suspension_reason VARCHAR(255) DEFAULT NULL");
        $this->addSql("UPDATE reviews SET status = 'approved' WHERE status IS NULL OR status = ''");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP driver_preferences, DROP suspended_at, DROP suspension_reason');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0FC69DE5E5');
        $this->addSql('DROP INDEX IDX_6970EB0FC69DE5E5 ON reviews');
        $this->addSql('ALTER TABLE reviews DROP validated_by_id, DROP status, DROP validated_at, DROP moderation_note');
    }
}
