<?php

declare(strict_types=1);

namespace Shared\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104173048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store how each CMS page presents itself to search engines; existing pages are listed, but the search page.';
    }

    public function up(Schema $schema): void
    {
        // The default only fills the existing pages; the application always sets the robots.
        $this->addSql("ALTER TABLE cms_page ADD meta_description VARCHAR(200) DEFAULT NULL, ADD robots VARCHAR(20) NOT NULL DEFAULT 'index,follow', ADD canonical_url VARCHAR(2048) DEFAULT NULL");
        $this->addSql('ALTER TABLE cms_page ALTER robots DROP DEFAULT');
        // Search engines advise against indexing internal search results, as new search pages do.
        $this->addSql("UPDATE cms_page SET robots = 'noindex,follow' WHERE type = 'search'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page DROP meta_description, DROP robots, DROP canonical_url');
    }
}
