<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The site shows how long a post takes to read, as it was when published.
 */
final class Version20261116214442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep how many minutes each post on the site takes to read.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_post ADD reading_minutes SMALLINT UNSIGNED NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_post DROP reading_minutes');
    }
}
