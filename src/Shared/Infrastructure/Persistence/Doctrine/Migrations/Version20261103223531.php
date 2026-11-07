<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103223531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the CMS page slug unique, so no two pages share an address.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cms_page DROP INDEX idx_cms_page_slug, ADD UNIQUE INDEX uniq_cms_page_slug (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cms_page DROP INDEX uniq_cms_page_slug, ADD INDEX idx_cms_page_slug (slug)');
    }
}
