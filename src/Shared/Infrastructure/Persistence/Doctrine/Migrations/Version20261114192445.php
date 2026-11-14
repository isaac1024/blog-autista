<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114192445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The visits of the day tell what kind of content they were to, the pages they were to before.';
    }

    public function up(Schema $schema): void
    {
        // The visits not counted yet were all to pages.
        $this->addSql("ALTER TABLE web_visit ADD content_type VARCHAR(10) NOT NULL DEFAULT 'page', CHANGE page_id content_id CHAR(36) NOT NULL");
        $this->addSql('ALTER TABLE web_visit ALTER content_type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_visit DROP content_type, CHANGE content_id page_id CHAR(36) NOT NULL');
    }
}
