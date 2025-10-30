<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop latitude/longitude columns from rides.
 */
final class Version20251030201700DropRideCoords extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop from_lat, from_lng, to_lat, to_lng columns from rides table';
    }

    public function up(Schema $schema): void
    {
        // MySQL compatible ALTER TABLE
        $this->addSql('ALTER TABLE rides DROP COLUMN from_lat, DROP COLUMN from_lng, DROP COLUMN to_lat, DROP COLUMN to_lng');
    }

    public function down(Schema $schema): void
    {
        // Recreate columns as VARCHAR(255) NULL (loosest form, per requirement)
        $this->addSql('ALTER TABLE rides ADD from_lat VARCHAR(255) DEFAULT NULL, ADD from_lng VARCHAR(255) DEFAULT NULL, ADD to_lat VARCHAR(255) DEFAULT NULL, ADD to_lng VARCHAR(255) DEFAULT NULL');
    }
}

