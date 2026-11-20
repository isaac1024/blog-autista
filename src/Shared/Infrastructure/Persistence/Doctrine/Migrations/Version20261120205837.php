<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120205837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the slug of each section the site was given, published or not, which the addresses of its posts name, in place of the one each post kept.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_section_slug (section_id CHAR(36) NOT NULL, slug VARCHAR(80) NOT NULL, PRIMARY KEY (section_id)) DEFAULT CHARACTER SET utf8mb4');
        // The slugs already known: those of the sections on the site, then
        // those the posts of the sections off it kept.
        $this->addSql("INSERT INTO web_section_slug (section_id, slug) SELECT id, slug FROM web_term WHERE taxonomy = 'section'");
        $this->addSql('INSERT IGNORE INTO web_section_slug (section_id, slug) SELECT section_id, MAX(section_slug) FROM web_post GROUP BY section_id');
        $this->addSql('ALTER TABLE web_post DROP section_slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_section_slug');
        $this->addSql('ALTER TABLE web_post ADD section_slug VARCHAR(80) NOT NULL');
    }
}
