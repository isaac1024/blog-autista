<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120235549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hold every column a file of the sitemap reads in its index, so the posts of a year are read from it alone, in order.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_web_sitemap_entry_file ON web_sitemap_entry');
        $this->addSql('CREATE INDEX idx_web_sitemap_entry_file ON web_sitemap_entry (file, path, lastmod, image_id, section_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_web_sitemap_entry_file ON web_sitemap_entry');
        $this->addSql('CREATE INDEX idx_web_sitemap_entry_file ON web_sitemap_entry (file, path)');
    }
}
