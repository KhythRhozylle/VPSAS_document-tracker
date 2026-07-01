<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701085550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE documents CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE system_settings RENAME INDEX uniq_8ca55eb05fa1e697 TO UNIQ_8CAF11475FA1E697');
        $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE documents CHANGE status status VARCHAR(50) DEFAULT \'Approved\' NOT NULL');
        $this->addSql('ALTER TABLE system_settings RENAME INDEX uniq_8caf11475fa1e697 TO UNIQ_8CA55EB05FA1E697');
        $this->addSql('ALTER TABLE users CHANGE roles roles JSON DEFAULT \'_utf8mb4\\\\\'\'[]\\\\\'\'\' NOT NULL');
    }
}
