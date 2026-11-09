<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109173325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Count the saves of each redirect, so one saved from a stale read is refused.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cms_redirect ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cms_redirect DROP version');
    }
}
