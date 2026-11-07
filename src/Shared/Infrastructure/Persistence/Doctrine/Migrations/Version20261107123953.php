<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261107123953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the pages on the site apart from the CMS, as it publishes them.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_page (id UUID NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL, content JSON NOT NULL, meta_description VARCHAR(200) DEFAULT NULL, robots VARCHAR(20) NOT NULL, first_published_at DATETIME NOT NULL, last_published_at DATETIME NOT NULL, last_event_on DATETIME NOT NULL, UNIQUE INDEX uniq_web_page_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_page');
    }
}
