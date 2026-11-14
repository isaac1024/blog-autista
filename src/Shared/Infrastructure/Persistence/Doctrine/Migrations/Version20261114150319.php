<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114150319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The site keeps the posts the CMS publishes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_post (id UUID NOT NULL, title VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL, section_id CHAR(36) NOT NULL, section_name VARCHAR(60) NOT NULL, section_slug VARCHAR(80) NOT NULL, author_id CHAR(36) NOT NULL, author_name VARCHAR(100) NOT NULL, tag_ids JSON NOT NULL, content JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, day DATE NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_post_address (section_slug, day, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_post');
    }
}
