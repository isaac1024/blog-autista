<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114133901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The CMS keeps the authors of the blog, with their avatars, and the site those it publishes.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cms_author (published_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL, bio JSON NOT NULL, name VARCHAR(100) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_cms_author_slug (slug), UNIQUE INDEX uniq_cms_author_published_slug (published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_author (id UUID NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL, avatar VARCHAR(64) NOT NULL, avatar_name VARCHAR(100) NOT NULL, bio JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_author_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cms_author');
        $this->addSql('DROP TABLE web_author');
    }
}
