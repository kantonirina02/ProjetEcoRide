<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030225321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Drop columns only if they still exist (idempotent for dev machines)
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['rides'])) {
            $table = $sm->introspectTable('rides');
            $existing = array_map(static fn($c) => $c->getName(), $table->getColumns());
            $toDrop = [];
            foreach (['from_lat','from_lng','to_lat','to_lng'] as $c) {
                if (in_array($c, $existing, true)) {
                    $toDrop[] = "DROP COLUMN $c";
                }
            }
            if (!empty($toDrop)) {
                $this->addSql('ALTER TABLE rides '.implode(', ', $toDrop));
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rides ADD from_lat NUMERIC(10, 7) NOT NULL, ADD from_lng NUMERIC(10, 7) NOT NULL, ADD to_lat NUMERIC(10, 7) NOT NULL, ADD to_lng NUMERIC(10, 7) NOT NULL');
    }
}
