<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115091212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'External resources: videos, posts, audios, articles, studies and documents of other sites, kept once and used by their id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cms_external_resource (url_hash VARCHAR(64) NOT NULL, title VARCHAR(200) NOT NULL, id UUID NOT NULL, kind VARCHAR(20) NOT NULL, provider VARCHAR(20) DEFAULT NULL, version JSON NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, availability VARCHAR(20) NOT NULL, checked_at DATETIME DEFAULT NULL, publication_revision INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_published_at DATETIME DEFAULT NULL, last_published_at DATETIME DEFAULT NULL, INDEX idx_cms_external_resource_availability (status, availability), UNIQUE INDEX uniq_cms_external_resource_url_hash (url_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cms_external_resource');
    }
}
