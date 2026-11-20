<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120234755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep every id of another resource in a native UUID column, as the ids of the aggregates: 16 bytes in each key that holds it instead of up to 144.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_relation CHANGE source_id source_id UUID NOT NULL, CHANGE target_id target_id UUID NOT NULL');
        $this->addSql('ALTER TABLE shared_redirect CHANGE target_id target_id UUID DEFAULT NULL, CHANGE origin_id origin_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE shared_visit CHANGE content_id content_id UUID NOT NULL');
        $this->addSql('ALTER TABLE web_post CHANGE section_id section_id UUID NOT NULL, CHANGE author_id author_id UUID NOT NULL, CHANGE opening_image_id opening_image_id UUID NOT NULL');
        $this->addSql('ALTER TABLE web_post_tag CHANGE term_id term_id UUID NOT NULL');
        $this->addSql('ALTER TABLE web_projection_checkpoint CHANGE aggregate_id aggregate_id UUID NOT NULL');
        $this->addSql('ALTER TABLE web_section_slug CHANGE section_id section_id UUID NOT NULL');
        $this->addSql('ALTER TABLE web_settings CHANGE default_image_id default_image_id UUID DEFAULT NULL, CHANGE author_id author_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE web_sitemap_entry CHANGE resource_id resource_id UUID NOT NULL, CHANGE image_id image_id UUID DEFAULT NULL, CHANGE section_id section_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_relation CHANGE source_id source_id VARCHAR(36) NOT NULL, CHANGE target_id target_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE shared_redirect CHANGE target_id target_id VARCHAR(36) DEFAULT NULL, CHANGE origin_id origin_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE shared_visit CHANGE content_id content_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE web_post CHANGE section_id section_id CHAR(36) NOT NULL, CHANGE author_id author_id CHAR(36) NOT NULL, CHANGE opening_image_id opening_image_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE web_post_tag CHANGE term_id term_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE web_projection_checkpoint CHANGE aggregate_id aggregate_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE web_section_slug CHANGE section_id section_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE web_settings CHANGE default_image_id default_image_id VARCHAR(36) DEFAULT NULL, CHANGE author_id author_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE web_sitemap_entry CHANGE resource_id resource_id CHAR(36) NOT NULL, CHANGE image_id image_id CHAR(36) DEFAULT NULL, CHANGE section_id section_id CHAR(36) DEFAULT NULL');
    }
}
