<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261107114251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publish CMS pages, with the version on the site and their dates, and queue domain events; existing pages become drafts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        // The defaults only fill the existing pages, which become drafts dated
        // now; the application always sets them.
        $this->addSql("ALTER TABLE cms_page ADD published_slug VARCHAR(80) DEFAULT NULL, ADD status VARCHAR(20) NOT NULL DEFAULT 'draft', ADD published_version JSON DEFAULT NULL, ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD first_published_at DATETIME DEFAULT NULL, ADD last_published_at DATETIME DEFAULT NULL");
        $this->addSql('ALTER TABLE cms_page ALTER status DROP DEFAULT, ALTER created_at DROP DEFAULT, ALTER updated_at DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_page_published_slug ON cms_page (published_slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX uniq_cms_page_published_slug ON cms_page');
        $this->addSql('ALTER TABLE cms_page DROP published_slug, DROP status, DROP published_version, DROP created_at, DROP updated_at, DROP first_published_at, DROP last_published_at');
    }
}
