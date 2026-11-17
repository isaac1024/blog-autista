<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Ui;

use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorResponse;

/**
 * An author on the site as search engines are told of them, wherever the
 * site names them: on their page, as who publishes the blog, and as who
 * signs a post. Each is one node of schema.org, told the same way.
 */
final class AuthorPerson
{
    /**
     * The absolute address of their page and the node of the person, with
     * their avatar.
     *
     * @return array{url: string, node: array<string, mixed>}
     */
    public static function node(AuthorResponse $author, UrlGeneratorInterface $urlGenerator): array
    {
        $url = $urlGenerator->generate('web_author', ['slug' => $author->slug], UrlGeneratorInterface::ABSOLUTE_URL);
        $home = $urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        return ['url' => $url, 'node' => StructuredData::person(
            $url,
            $author->name,
            StructuredData::absolute($home, SiteMedia::avatar($author->avatarName, $author->name)['src']),
            $author->metaDescription,
        )];
    }
}
