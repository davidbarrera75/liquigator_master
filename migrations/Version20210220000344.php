<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210220000344 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyeccion ADD fecha_inicial DATE NOT NULL, ADD fecha_final DATE DEFAULT NULL, ADD titulo VARCHAR(255) NOT NULL, ADD json_data JSON DEFAULT NULL, DROP anio, DROP semanas, DROP pension');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyeccion ADD anio INT NOT NULL, ADD semanas DOUBLE PRECISION NOT NULL, ADD pension DOUBLE PRECISION NOT NULL, DROP fecha_inicial, DROP fecha_final, DROP titulo, DROP json_data');
    }
}
