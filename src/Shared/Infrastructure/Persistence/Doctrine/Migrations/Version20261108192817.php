<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261108192817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the settings of the site: the name of the blog, its default image and its menus, in the CMS and on the site.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cms_settings (site VARCHAR(20) NOT NULL, id UUID NOT NULL, status VARCHAR(20) NOT NULL, published_version JSON DEFAULT NULL, default_image_id UUID DEFAULT NULL, main_menu JSON NOT NULL, footer_menu JSON NOT NULL, name VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_cms_settings_site (site), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE web_settings (id UUID NOT NULL, name VARCHAR(60) NOT NULL, default_image_id VARCHAR(36) DEFAULT NULL, main_menu JSON NOT NULL, footer_menu JSON NOT NULL, last_event_on DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cms_settings');
        $this->addSql('DROP TABLE web_settings');
    }
}
