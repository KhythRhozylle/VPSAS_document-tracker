<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and notifications tables with default profile seed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            department VARCHAR(150) DEFAULT NULL,
            job_title VARCHAR(150) DEFAULT NULL,
            profile_picture VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE notifications (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message LONGTEXT NOT NULL,
            type VARCHAR(20) NOT NULL,
            link VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $this->addSql(
            "INSERT INTO users (first_name, last_name, email, phone, department, job_title, profile_picture, created_at, updated_at)
             VALUES ('Admin', 'User', 'admin@vpsas.local', NULL, 'Document Management', 'System Administrator', NULL, :now, :now)",
            ['now' => $now],
        );

        $this->addSql(
            "INSERT INTO notifications (title, message, type, link, is_read, created_at) VALUES
            ('Welcome to VPSAS', 'Your document tracking system is ready. Start by adding documents or browsing reports.', 'info', '/documents', 0, :now),
            ('Profile setup', 'Click your profile icon to add your details and upload a profile picture.', 'info', NULL, 0, :now)",
            ['now' => $now],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE users');
    }
}
