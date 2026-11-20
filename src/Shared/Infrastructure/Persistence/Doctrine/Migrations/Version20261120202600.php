<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120202600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tell a post of the site by the day it was first published and its slug alone, and keep the section of a post in its entry of the sitemap, whose slug its address names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_web_post_address ON web_post');
        $this->addSql('CREATE UNIQUE INDEX uniq_web_post_address ON web_post (day, slug)');
        $this->addSql('ALTER TABLE web_sitemap_entry ADD section_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_web_sitemap_entry_section ON web_sitemap_entry (section_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_web_post_address ON web_post');
        $this->addSql('CREATE UNIQUE INDEX uniq_web_post_address ON web_post (section_slug, day, slug)');
        $this->addSql('DROP INDEX idx_web_sitemap_entry_section ON web_sitemap_entry');
        $this->addSql('ALTER TABLE web_sitemap_entry DROP section_id');
    }
}
