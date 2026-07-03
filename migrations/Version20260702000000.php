<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow documents.amount to be nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents CHANGE amount amount NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents CHANGE amount amount NUMERIC(12, 2) NOT NULL');
    }
}
