<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The settings name the author of the blog, whom the site tells search
 * engines publishes it; settings saved before name none.
 */
final class Version20261116195033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The settings, in the CMS and on the site, keep the author of the blog.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_settings ADD author_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE web_settings ADD author_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_settings DROP author_id');
        $this->addSql('ALTER TABLE web_settings DROP author_id');
    }
}
