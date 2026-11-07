<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103231314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the rich text content of each CMS page; the existing ones start empty.';
    }

    public function up(Schema $schema): void
    {
        // The default only fills the existing pages with an empty document;
        // the application always sets the content.
        $this->addSql('ALTER TABLE cms_page ADD content JSON NOT NULL DEFAULT \'{"version":1,"blocks":[]}\'');
        $this->addSql('ALTER TABLE cms_page ALTER content DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page DROP content');
    }
}
