<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201230231711 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalogo (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, default_value VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parametros (id INT AUTO_INCREMENT NOT NULL, catalogo_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, param1 VARCHAR(255) DEFAULT NULL, param2 VARCHAR(255) DEFAULT NULL, INDEX IDX_E09691174979D753 (catalogo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parametros ADD CONSTRAINT FK_E09691174979D753 FOREIGN KEY (catalogo_id) REFERENCES catalogo (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parametros DROP FOREIGN KEY FK_E09691174979D753');
        $this->addSql('DROP TABLE catalogo');
        $this->addSql('DROP TABLE parametros');
    }
}
