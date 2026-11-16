<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The sitemap of the site keeps an entry for each address search engines
 * may index, in its file, and each file with when it last changed.
 */
final class Version20261116202115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the sitemap of the site: its entries and its files.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_sitemap_entry (resource_id CHAR(36) NOT NULL, file VARCHAR(20) NOT NULL, path VARCHAR(255) NOT NULL, lastmod DATETIME NOT NULL, image_id CHAR(36) DEFAULT NULL, term_ids JSON NOT NULL, INDEX idx_web_sitemap_entry_file (file, path), PRIMARY KEY (resource_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_sitemap_file (name VARCHAR(20) NOT NULL, entries INT NOT NULL, lastmod DATETIME NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_sitemap_entry');
        $this->addSql('DROP TABLE web_sitemap_file');
    }
}
