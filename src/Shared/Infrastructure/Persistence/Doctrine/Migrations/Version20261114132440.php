<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114132440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The site keeps the terms the CMS publishes, each at the address of its taxonomy.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE web_term (id UUID NOT NULL, taxonomy VARCHAR(20) NOT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(80) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_term_slug (taxonomy, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE web_term');
    }
}
