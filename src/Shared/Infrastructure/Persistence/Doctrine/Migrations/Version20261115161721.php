<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115161721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Posts open with an image of the library, needed to publish them, which the site paints and shares them with.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_post ADD opening_image_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE web_post ADD opening_image_id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms_post DROP opening_image_id');
        $this->addSql('ALTER TABLE web_post DROP opening_image_id');
    }
}
