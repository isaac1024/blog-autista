<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121195029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The schema of the blog as it first goes to production: the tables of the CMS, of the site, those they share, and the queue of messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cms_author (published_slug VARCHAR(80) DEFAULT NULL, last_site_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL, bio JSON NOT NULL, name VARCHAR(100) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_author_last_site_slug (last_site_slug), UNIQUE INDEX uniq_cms_author_slug (slug), UNIQUE INDEX uniq_cms_author_published_slug (published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_external_resource (url_hash VARCHAR(64) NOT NULL, title VARCHAR(200) NOT NULL, id UUID NOT NULL, kind VARCHAR(20) NOT NULL, provider VARCHAR(20) DEFAULT NULL, version JSON NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, availability VARCHAR(20) NOT NULL, checked_at DATETIME DEFAULT NULL, publication_revision INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_external_resource_availability (status, availability), UNIQUE INDEX uniq_cms_external_resource_url_hash (url_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_image (id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT DEFAULT 0 NOT NULL, caption JSON DEFAULT NULL, alt VARCHAR(125) NOT NULL, path VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, FULLTEXT INDEX ftx_cms_image_alt (alt), UNIQUE INDEX uniq_cms_image_path (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_page (published_slug VARCHAR(80) DEFAULT NULL, last_site_slug VARCHAR(80) DEFAULT NULL, unique_type VARCHAR(20) DEFAULT NULL, id UUID NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT DEFAULT 0 NOT NULL, slug VARCHAR(80) DEFAULT NULL, content JSON NOT NULL, title VARCHAR(100) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_page_last_site_slug (last_site_slug), FULLTEXT INDEX ftx_cms_page_title (title), UNIQUE INDEX uniq_cms_page_slug (slug), UNIQUE INDEX uniq_cms_page_published_slug (published_slug), UNIQUE INDEX uniq_cms_page_unique_type (unique_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_post (published_day DATE DEFAULT NULL, published_slug VARCHAR(80) DEFAULT NULL, last_day DATE DEFAULT NULL, last_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, opening_image_id UUID DEFAULT NULL, content JSON NOT NULL, title VARCHAR(100) NOT NULL, section_id UUID DEFAULT NULL, author_id UUID DEFAULT NULL, tag_ids JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_post_last_address (last_day, last_slug), FULLTEXT INDEX ftx_cms_post_title (title), INDEX idx_cms_post_id (id), UNIQUE INDEX uniq_cms_post_published_address (published_day, published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_relation (id UUID NOT NULL, source_type VARCHAR(20) NOT NULL, source_id UUID NOT NULL, target_type VARCHAR(20) NOT NULL, target_id UUID NOT NULL, INDEX idx_cms_relation_target (target_type, target_id, source_type, source_id), UNIQUE INDEX uniq_cms_relation (source_type, source_id, target_type, target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_settings (site VARCHAR(20) NOT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT DEFAULT 0 NOT NULL, default_image_id UUID DEFAULT NULL, author_id UUID DEFAULT NULL, main_menu JSON NOT NULL, footer_menu JSON NOT NULL, name VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_cms_settings_site (site), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_term (published_slug VARCHAR(80) DEFAULT NULL, last_site_slug VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, taxonomy VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, publication_revision INT NOT NULL, slug VARCHAR(80) DEFAULT NULL, name VARCHAR(60) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_term_last_site_slug (taxonomy, last_site_slug), UNIQUE INDEX uniq_cms_term_slug (taxonomy, slug), UNIQUE INDEX uniq_cms_term_published_slug (taxonomy, published_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shared_redirect (version INT DEFAULT 1 NOT NULL, id UUID NOT NULL, source VARCHAR(255) NOT NULL, target_type VARCHAR(20) DEFAULT NULL, target_id UUID DEFAULT NULL, origin_type VARCHAR(20) DEFAULT NULL, origin_id UUID DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_shared_redirect_target (target_type, target_id), INDEX idx_shared_redirect_origin (origin_type, origin_id), UNIQUE INDEX uniq_shared_redirect_source (source), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shared_visit (id UUID NOT NULL, content_type VARCHAR(10) NOT NULL, content_id UUID NOT NULL, day DATE NOT NULL, channel VARCHAR(20) NOT NULL, source VARCHAR(253) DEFAULT NULL, reader CHAR(64) NOT NULL, device VARCHAR(10) NOT NULL, country CHAR(2) DEFAULT NULL, INDEX idx_shared_visit_day (day), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shared_visit_total (day DATE NOT NULL, dimension VARCHAR(20) NOT NULL, value VARCHAR(253) NOT NULL, views INT NOT NULL, visitors INT NOT NULL, PRIMARY KEY (day, dimension, value)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_author (id UUID NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL, avatar VARCHAR(64) NOT NULL, avatar_name VARCHAR(100) NOT NULL, bio JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_author_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_external_resource (id UUID NOT NULL, kind VARCHAR(20) NOT NULL, provider VARCHAR(20) DEFAULT NULL, locator VARCHAR(300) DEFAULT NULL, url VARCHAR(2000) NOT NULL, title VARCHAR(200) NOT NULL, summary VARCHAR(300) DEFAULT NULL, language VARCHAR(5) NOT NULL, details JSON NOT NULL, image VARCHAR(64) DEFAULT NULL, image_shape VARCHAR(20) DEFAULT NULL, available TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_image (id UUID NOT NULL, alt VARCHAR(125) NOT NULL, caption JSON NOT NULL, path VARCHAR(64) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_page (id UUID NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(100) NOT NULL, slug VARCHAR(80) DEFAULT NULL, content JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_page_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_post (id UUID NOT NULL, title VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL, section_id UUID NOT NULL, section_name VARCHAR(60) NOT NULL, author_id UUID NOT NULL, author_name VARCHAR(100) NOT NULL, tag_ids JSON NOT NULL, opening_image_id UUID NOT NULL, content JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, search_text MEDIUMTEXT NOT NULL, reading_minutes SMALLINT UNSIGNED NOT NULL, robots VARCHAR(20) NOT NULL, day DATE NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, INDEX idx_web_post_listing (first_published_at, id), INDEX idx_web_post_section_listing (section_id, first_published_at, id), INDEX idx_web_post_author_listing (author_id, first_published_at, id), FULLTEXT INDEX ft_web_post_search (title, meta_description, search_text), UNIQUE INDEX uniq_web_post_address (day, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_post_tag (post_id UUID NOT NULL, term_id UUID NOT NULL, first_published_at DATETIME NOT NULL, INDEX idx_web_post_tag_listing (term_id, first_published_at, post_id), PRIMARY KEY (post_id, term_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_projection_checkpoint (projection VARCHAR(32) NOT NULL, aggregate_id UUID NOT NULL, revision INT NOT NULL, PRIMARY KEY (projection, aggregate_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_section_slug (section_id UUID NOT NULL, slug VARCHAR(80) NOT NULL, PRIMARY KEY (section_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_settings (id UUID NOT NULL, name VARCHAR(60) NOT NULL, default_image_id UUID DEFAULT NULL, author_id UUID DEFAULT NULL, main_menu JSON NOT NULL, footer_menu JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_sitemap_entry (resource_id UUID NOT NULL, file VARCHAR(20) NOT NULL, path VARCHAR(255) NOT NULL, lastmod DATETIME NOT NULL, image_id UUID DEFAULT NULL, term_ids JSON NOT NULL, section_id UUID DEFAULT NULL, INDEX idx_web_sitemap_entry_file (file, path, lastmod, image_id, section_id), INDEX idx_web_sitemap_entry_image (image_id), INDEX idx_web_sitemap_entry_section (section_id), PRIMARY KEY (resource_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_sitemap_file (name VARCHAR(20) NOT NULL, entries INT NOT NULL, lastmod DATETIME NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_term (id UUID NOT NULL, taxonomy VARCHAR(20) NOT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(80) NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, UNIQUE INDEX uniq_web_term_slug (taxonomy, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cms_author');
        $this->addSql('DROP TABLE cms_external_resource');
        $this->addSql('DROP TABLE cms_image');
        $this->addSql('DROP TABLE cms_page');
        $this->addSql('DROP TABLE cms_post');
        $this->addSql('DROP TABLE cms_relation');
        $this->addSql('DROP TABLE cms_settings');
        $this->addSql('DROP TABLE cms_term');
        $this->addSql('DROP TABLE shared_redirect');
        $this->addSql('DROP TABLE shared_visit');
        $this->addSql('DROP TABLE shared_visit_total');
        $this->addSql('DROP TABLE web_author');
        $this->addSql('DROP TABLE web_external_resource');
        $this->addSql('DROP TABLE web_image');
        $this->addSql('DROP TABLE web_page');
        $this->addSql('DROP TABLE web_post');
        $this->addSql('DROP TABLE web_post_tag');
        $this->addSql('DROP TABLE web_projection_checkpoint');
        $this->addSql('DROP TABLE web_section_slug');
        $this->addSql('DROP TABLE web_settings');
        $this->addSql('DROP TABLE web_sitemap_entry');
        $this->addSql('DROP TABLE web_sitemap_file');
        $this->addSql('DROP TABLE web_term');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
