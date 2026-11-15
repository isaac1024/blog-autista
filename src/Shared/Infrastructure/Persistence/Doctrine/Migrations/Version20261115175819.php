<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115175819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Images and settings keep when they were first and last published, as the other publishable resources do; those published before are dated by their last change, the best known.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_image ADD first_published_at DATETIME DEFAULT NULL, ADD last_published_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE cms_settings ADD first_published_at DATETIME DEFAULT NULL, ADD last_published_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE cms_image SET first_published_at = updated_at, last_published_at = updated_at WHERE status <> 'draft'");
        $this->addSql("UPDATE cms_settings SET first_published_at = updated_at, last_published_at = updated_at WHERE status <> 'draft'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_image DROP first_published_at, DROP last_published_at');
        $this->addSql('ALTER TABLE cms_settings DROP first_published_at, DROP last_published_at');
    }
}
