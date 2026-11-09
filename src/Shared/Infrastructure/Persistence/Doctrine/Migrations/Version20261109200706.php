<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109200706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the visits to the site until their day is counted, and what is left of them once counted.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE web_visit (id UUID NOT NULL, page_id CHAR(36) NOT NULL, day DATE NOT NULL, channel VARCHAR(20) NOT NULL, source VARCHAR(253) DEFAULT NULL, reader CHAR(64) NOT NULL, device VARCHAR(10) NOT NULL, country CHAR(2) DEFAULT NULL, INDEX idx_web_visit_day (day), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_visit_total (day DATE NOT NULL, dimension VARCHAR(20) NOT NULL, value VARCHAR(253) NOT NULL, views INT NOT NULL, visitors INT NOT NULL, PRIMARY KEY (day, dimension, value)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE web_visit');
        $this->addSql('DROP TABLE web_visit_total');
    }
}
