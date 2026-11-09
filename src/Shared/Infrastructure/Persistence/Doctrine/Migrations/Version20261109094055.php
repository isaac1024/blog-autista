<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109094055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order publication events by revision: count them in the CMS, keep the last one each projection of the site applied, and drop the times the site ordered them by.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_projection_checkpoint (projection VARCHAR(32) NOT NULL, aggregate_id VARCHAR(36) NOT NULL, revision INT NOT NULL, PRIMARY KEY (projection, aggregate_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cms_image ADD publication_revision INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE cms_page ADD publication_revision INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE cms_settings ADD publication_revision INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE web_image DROP last_event_on');
        $this->addSql('ALTER TABLE web_page DROP last_event_on');
        $this->addSql('ALTER TABLE web_settings DROP last_event_on');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_projection_checkpoint');
        $this->addSql('ALTER TABLE cms_image DROP publication_revision');
        $this->addSql('ALTER TABLE cms_page DROP publication_revision');
        $this->addSql('ALTER TABLE cms_settings DROP publication_revision');
        $this->addSql('ALTER TABLE web_image ADD last_event_on DATETIME NOT NULL');
        $this->addSql('ALTER TABLE web_page ADD last_event_on DATETIME NOT NULL');
        $this->addSql('ALTER TABLE web_settings ADD last_event_on DATETIME NOT NULL');
    }
}
