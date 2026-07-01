<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add authentication fields, activity logs table, and default users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD password VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE users ADD roles JSON DEFAULT ('[]') NOT NULL");
        $this->addSql('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');

        $this->addSql('CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            full_name VARCHAR(200) NOT NULL,
            email VARCHAR(180) NOT NULL,
            role VARCHAR(20) NOT NULL,
            action VARCHAR(50) NOT NULL,
            module VARCHAR(100) NOT NULL,
            record_id INT DEFAULT NULL,
            old_data JSON DEFAULT NULL,
            new_data JSON DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_activity_created_at (created_at),
            INDEX idx_activity_action (action),
            INDEX idx_activity_role (role),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $adminHash = '$2y$12$OPst4xhFZciVB.Tf.33J0OFfsH0hoTpCvzCT0Rm51brPtcOup2THe';
        $staffHash = '$2y$12$M6ZmPqgNn56QBXak4ffqkeeWase9yefzwXn/NpAv.vn8NphVuFitK';

        $this->addSql(
            "UPDATE users SET password = :password, roles = :roles WHERE email = 'admin@vpsas.local'",
            [
                'password' => $adminHash,
                'roles' => json_encode(['ROLE_ADMIN']),
            ],
        );

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $this->addSql(
            "INSERT INTO users (first_name, last_name, email, password, roles, phone, department, job_title, profile_picture, created_at, updated_at)
             SELECT 'Jane', 'Smith', 'staff@vpsas.local', :password, :roles, NULL, 'Records Office', 'Document Staff', NULL, :now, :now
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'staff@vpsas.local')",
            [
                'password' => $staffHash,
                'roles' => json_encode(['ROLE_STAFF']),
                'now' => $now,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('DROP INDEX UNIQ_1483A5E9E7927C74 ON users');
        $this->addSql('ALTER TABLE users DROP password, DROP roles');
    }
}
