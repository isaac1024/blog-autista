<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109130934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the redirects of the CMS, keep the slug each page last had on the site, and find images by the path of their file.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cms_redirect (id UUID NOT NULL, revision INT NOT NULL, source VARCHAR(255) NOT NULL, target_type VARCHAR(20) DEFAULT NULL, target_id VARCHAR(36) DEFAULT NULL, origin_type VARCHAR(20) DEFAULT NULL, origin_id VARCHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_cms_redirect_target (target_type, target_id), INDEX idx_cms_redirect_origin (origin_type, origin_id), UNIQUE INDEX uniq_cms_redirect_source (source), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_image_path ON cms_image (path)');
        $this->addSql('ALTER TABLE cms_page ADD last_site_slug VARCHAR(80) DEFAULT NULL');
        // A page published, on the site or taken off it since, last had there
        // the slug of the version last published.
        $this->addSql("UPDATE cms_page SET last_site_slug = JSON_VALUE(published_version, '$.slug') WHERE published_version IS NOT NULL");
        $this->addSql('CREATE INDEX idx_cms_page_last_site_slug ON cms_page (last_site_slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cms_redirect');
        $this->addSql('DROP INDEX uniq_cms_image_path ON cms_image');
        $this->addSql('DROP INDEX idx_cms_page_last_site_slug ON cms_page');
        $this->addSql('ALTER TABLE cms_page DROP last_site_slug');
    }
}
