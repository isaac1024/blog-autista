<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117203454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Name the tables of the visits after both contexts: the site writes them, and the CMS reads them for its report.';
    }

    public function up(Schema $schema): void
    {
        // Renamed, so the visits counted stay.
        $this->addSql('RENAME TABLE web_visit TO shared_visit, web_visit_total TO shared_visit_total');
        $this->addSql('ALTER TABLE shared_visit RENAME INDEX idx_web_visit_day TO idx_shared_visit_day');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_visit RENAME INDEX idx_shared_visit_day TO idx_web_visit_day');
        $this->addSql('RENAME TABLE shared_visit TO web_visit, shared_visit_total TO web_visit_total');
    }
}
