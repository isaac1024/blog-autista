<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Shared\Domain\RichText\RichText;

/**
 * The posts on the site are searched by their title, their summary and what
 * their content says, through a full-text index. The posts already on the
 * site take the plain text of their content once the index exists, as the
 * site writes it on every publication.
 */
final class Version20261116193947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Search the posts on the site by title, summary and content text, through a full-text index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_post ADD search_text MEDIUMTEXT NOT NULL');
        $this->addSql('CREATE FULLTEXT INDEX ft_web_post_search ON web_post (title, meta_description, search_text)');
    }

    #[\Override]
    public function postUp(Schema $schema): void
    {
        foreach ($this->connection->fetchAllKeyValue('SELECT id, content FROM web_post') as $id => $content) {
            $this->connection->update('web_post', [
                'search_text' => RichText::fromArray(json_decode((string) $content, true, flags: \JSON_THROW_ON_ERROR))->plainText(),
            ], ['id' => $id]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ft_web_post_search ON web_post');
        $this->addSql('ALTER TABLE web_post DROP search_text');
    }
}
