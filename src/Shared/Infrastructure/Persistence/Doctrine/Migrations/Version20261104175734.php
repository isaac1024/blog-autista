<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104175734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the canonical URL of CMS pages: a page is always canonical at its own address.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page DROP canonical_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_page ADD canonical_url VARCHAR(2048) DEFAULT NULL');
    }
}
