<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251113000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute payout_released_at sur rides et colonnes feedback sur ride_participants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE rides ADD payout_released_at DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE ride_participants ADD feedback_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $this->addSql("ALTER TABLE ride_participants ADD feedback_at DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE ride_participants ADD feedback_note LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ride_participants DROP feedback_status');
        $this->addSql('ALTER TABLE ride_participants DROP feedback_at');
        $this->addSql('ALTER TABLE ride_participants DROP feedback_note');
        $this->addSql('ALTER TABLE rides DROP payout_released_at');
    }
}
