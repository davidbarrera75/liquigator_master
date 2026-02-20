<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabla resumen_semanas para almacenar el resumen de semanas cotizadas de Colpensiones
 */
final class Version20260220090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla resumen_semanas para resumen de semanas cotizadas por empleador (Colpensiones)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE resumen_semanas (
            id INT AUTO_INCREMENT NOT NULL,
            info_id INT DEFAULT NULL,
            nombre_razon_social VARCHAR(500) NOT NULL,
            desde VARCHAR(20) NOT NULL,
            hasta VARCHAR(20) NOT NULL,
            ultimo_salario VARCHAR(50) NOT NULL,
            semanas VARCHAR(20) NOT NULL,
            sim VARCHAR(20) NOT NULL,
            total VARCHAR(20) NOT NULL,
            INDEX IDX_RESUMEN_INFO (info_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_RESUMEN_INFO FOREIGN KEY (info_id) REFERENCES information (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE resumen_semanas');
    }
}
