<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114131737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The CMS keeps the terms of its taxonomies, sections and tags, each with the version being edited and the one on the site.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cms_term (published_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, taxonomy VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, name VARCHAR(60) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_cms_term_slug (taxonomy, slug), UNIQUE INDEX uniq_cms_term_published_slug (taxonomy, published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cms_term');
    }
}
