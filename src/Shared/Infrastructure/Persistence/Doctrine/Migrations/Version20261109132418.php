<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109132418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the redirects of the site, which it answers addresses with nothing of its own by.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE web_redirect (id UUID NOT NULL, source VARCHAR(255) NOT NULL, target_type VARCHAR(20) DEFAULT NULL, target_id VARCHAR(36) DEFAULT NULL, origin_type VARCHAR(20) DEFAULT NULL, origin_id VARCHAR(36) DEFAULT NULL, changed_at DATETIME NOT NULL, INDEX idx_web_redirect_source (source), INDEX idx_web_redirect_origin (origin_type, origin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE web_redirect');
    }
}
