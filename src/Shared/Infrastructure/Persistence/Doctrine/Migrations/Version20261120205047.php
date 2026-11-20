<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120205047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tell a post of the CMS by the day it was first published and its slug alone: the section its address names is read from the section.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_post_last_address ON cms_post');
        $this->addSql('DROP INDEX uniq_cms_post_published_address ON cms_post');
        $this->addSql('ALTER TABLE cms_post DROP published_section_slug, DROP last_section_slug');
        $this->addSql('CREATE INDEX idx_cms_post_last_address ON cms_post (last_day, last_slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_post_published_address ON cms_post (published_day, published_slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_post_last_address ON cms_post');
        $this->addSql('DROP INDEX uniq_cms_post_published_address ON cms_post');
        $this->addSql('ALTER TABLE cms_post ADD published_section_slug VARCHAR(80) DEFAULT NULL, ADD last_section_slug VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cms_post_last_address ON cms_post (last_section_slug, last_day, last_slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_post_published_address ON cms_post (published_section_slug, published_day, published_slug)');
    }
}
