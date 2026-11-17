<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117214234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the users of a resource by their kind and id, so the CMS reads a slice of them without sorting them all.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_relation_target ON cms_relation');
        $this->addSql('CREATE INDEX idx_cms_relation_target ON cms_relation (target_type, target_id, source_type, source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_relation_target ON cms_relation');
        $this->addSql('CREATE INDEX idx_cms_relation_target ON cms_relation (target_type, target_id)');
    }
}
