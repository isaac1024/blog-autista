<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117214458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the ids of the posts of the CMS alone, so a page deep in its list skips them without reading every post.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_cms_post_id ON cms_post (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cms_post_id ON cms_post');
    }
}
