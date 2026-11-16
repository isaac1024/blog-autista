<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The sitemap finds the posts that open with an image, which change with it.
 */
final class Version20261116202819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the entries of the sitemap by the image their post opens with.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_web_sitemap_entry_image ON web_sitemap_entry (image_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_web_sitemap_entry_image ON web_sitemap_entry');
    }
}
