<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The posts on the site are listed newest first through indexes: those of
 * a section, of an author or of the whole site through web_post, those of a
 * tag through web_post_tag, an index of the tags each post keeps, filled
 * from them for the posts already on the site.
 */
final class Version20261116191015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the posts on the site for their lists: by section, author and first publication, and by tag in web_post_tag.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_post_tag (post_id UUID NOT NULL, term_id CHAR(36) NOT NULL, first_published_at DATETIME NOT NULL, INDEX idx_web_post_tag_listing (term_id, first_published_at, post_id), PRIMARY KEY (post_id, term_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO web_post_tag (post_id, term_id, first_published_at)
            SELECT p.id, t.term_id, p.first_published_at
            FROM web_post p, JSON_TABLE(p.tag_ids, '$[*]' COLUMNS (term_id CHAR(36) PATH '$')) t
            SQL);
        $this->addSql('CREATE INDEX idx_web_post_listing ON web_post (first_published_at, id)');
        $this->addSql('CREATE INDEX idx_web_post_section_listing ON web_post (section_id, first_published_at, id)');
        $this->addSql('CREATE INDEX idx_web_post_author_listing ON web_post (author_id, first_published_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE web_post_tag');
        $this->addSql('DROP INDEX idx_web_post_listing ON web_post');
        $this->addSql('DROP INDEX idx_web_post_section_listing ON web_post');
        $this->addSql('DROP INDEX idx_web_post_author_listing ON web_post');
    }
}
