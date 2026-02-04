<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201209130701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO configuration(`name`,`default_vslue`,`config_values`) values('pensiones',null,'colpensiones,skandia,porvenir,colfondos,proteccion')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM configuration WHERE name='pensiones'");
        // this down() migration is auto-generated, please modify it to your needs

    }
}
