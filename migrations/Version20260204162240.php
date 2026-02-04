<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sentencia C-197 de 2023 - Campo género para diferenciación de semanas exigidas
 */
final class Version20260204162240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campo genero a information para Sentencia C-197 de 2023';
    }

    public function up(Schema $schema): void
    {
        // Agregar campo genero con valor por defecto 'M' (Masculino)
        $this->addSql('ALTER TABLE information ADD genero VARCHAR(1) DEFAULT \'M\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE information DROP genero');
    }
}
