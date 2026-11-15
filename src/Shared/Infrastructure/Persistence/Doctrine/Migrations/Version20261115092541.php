<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115092541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The external resources on the site, as the CMS last published them, and whether they are available.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_external_resource (id UUID NOT NULL, kind VARCHAR(20) NOT NULL, provider VARCHAR(20) DEFAULT NULL, locator VARCHAR(300) DEFAULT NULL, url VARCHAR(2000) NOT NULL, title VARCHAR(200) NOT NULL, summary VARCHAR(300) DEFAULT NULL, language VARCHAR(5) NOT NULL, details JSON NOT NULL, image VARCHAR(64) DEFAULT NULL, image_shape VARCHAR(20) DEFAULT NULL, available TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_external_resource');
    }
}
