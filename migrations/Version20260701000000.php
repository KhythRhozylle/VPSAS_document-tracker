<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create documents table for VPSAS document tracker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE documents (
            id INT AUTO_INCREMENT NOT NULL,
            date_approved DATE NOT NULL,
            campus VARCHAR(255) NOT NULL,
            document_type VARCHAR(255) NOT NULL,
            particulars LONGTEXT NOT NULL,
            amount NUMERIC(12, 2) NOT NULL,
            nature VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE documents');
    }
}
