<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117203252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Name the table of the redirects after both contexts: the CMS writes it, and the site reads it the moment it changes.';
    }

    public function up(Schema $schema): void
    {
        // Renamed, so the redirects kept stay.
        $this->addSql('RENAME TABLE cms_redirect TO shared_redirect');
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX idx_cms_redirect_target TO idx_shared_redirect_target');
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX idx_cms_redirect_origin TO idx_shared_redirect_origin');
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX uniq_cms_redirect_source TO uniq_shared_redirect_source');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX uniq_shared_redirect_source TO uniq_cms_redirect_source');
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX idx_shared_redirect_origin TO idx_cms_redirect_origin');
        $this->addSql('ALTER TABLE shared_redirect RENAME INDEX idx_shared_redirect_target TO idx_cms_redirect_target');
        $this->addSql('RENAME TABLE shared_redirect TO cms_redirect');
    }
}
