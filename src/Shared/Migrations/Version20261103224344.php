<?php

declare(strict_types=1);

namespace Shared\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103224344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the type of each CMS page; the existing ones are generic pages.';
    }

    public function up(Schema $schema): void
    {
        // Every page created so far came from the CMS, so it is a generic page.
        // The default only fills those rows: the application always sets the type.
        $this->addSql("ALTER TABLE cms_page ADD type VARCHAR(20) NOT NULL DEFAULT 'standard'");
        $this->addSql('ALTER TABLE cms_page ALTER type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page DROP type');
    }
}
