<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210104011540 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pdfreport (id INT AUTO_INCREMENT NOT NULL, information_id INT DEFAULT NULL, created_at DATETIME NOT NULL, name VARCHAR(255) NOT NULL, full_path VARCHAR(255) NOT NULL, INDEX IDX_DF80D6C22EF03101 (information_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pdfreport ADD CONSTRAINT FK_DF80D6C22EF03101 FOREIGN KEY (information_id) REFERENCES information (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE pdfreport');
    }
}
