<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114145302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The CMS keeps the posts of the blog, filed under sections, signed by authors and labelled with tags.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cms_post (published_section_slug VARCHAR(80) DEFAULT NULL, published_day DATE DEFAULT NULL, published_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, content JSON NOT NULL, title VARCHAR(100) NOT NULL, section_id UUID DEFAULT NULL, author_id UUID DEFAULT NULL, tag_ids JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, FULLTEXT INDEX ftx_cms_post_title (title), UNIQUE INDEX uniq_cms_post_published_address (published_section_slug, published_day, published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cms_post');
    }
}
