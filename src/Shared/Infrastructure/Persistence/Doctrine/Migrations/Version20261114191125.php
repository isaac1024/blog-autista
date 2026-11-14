<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114191125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The posts, sections, tags and authors keep the last address they had on the site.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_author ADD last_site_slug VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cms_author_last_site_slug ON cms_author (last_site_slug)');
        $this->addSql('ALTER TABLE cms_post ADD last_section_slug VARCHAR(80) DEFAULT NULL, ADD last_day DATE DEFAULT NULL, ADD last_slug VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cms_post_last_address ON cms_post (last_section_slug, last_day, last_slug)');
        $this->addSql('ALTER TABLE cms_term ADD last_site_slug VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cms_term_last_site_slug ON cms_term (taxonomy, last_site_slug)');
        // What is on the site has that address as its last.
        $this->addSql('UPDATE cms_author SET last_site_slug = published_slug WHERE published_slug IS NOT NULL');
        $this->addSql('UPDATE cms_term SET last_site_slug = published_slug WHERE published_slug IS NOT NULL');
        $this->addSql('UPDATE cms_post SET last_section_slug = published_section_slug, last_day = published_day, last_slug = published_slug WHERE published_slug IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_author_last_site_slug ON cms_author');
        $this->addSql('ALTER TABLE cms_author DROP last_site_slug');
        $this->addSql('DROP INDEX idx_cms_post_last_address ON cms_post');
        $this->addSql('ALTER TABLE cms_post DROP last_section_slug, DROP last_day, DROP last_slug');
        $this->addSql('DROP INDEX idx_cms_term_last_site_slug ON cms_term');
        $this->addSql('ALTER TABLE cms_term DROP last_site_slug');
    }
}
