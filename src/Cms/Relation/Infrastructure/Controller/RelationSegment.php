<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Redirect\Domain\ResourceType as RedirectResourceType;
use Cms\Relation\Domain\ResourceType;

/**
 * The section of the CMS a resource is edited under, such as /pages, as the
 * page that tells where it is used names it: what kind of resource it is to
 * the relations and to the redirects, where it is edited, and the texts of
 * the page, under cms.relations.{text}.
 */
enum RelationSegment: string
{
    case Pages = 'pages';
    case Images = 'images';
    case Posts = 'posts';
    case Sections = 'sections';
    case Tags = 'tags';
    case Authors = 'authors';

    /**
     * What a route takes as the segment.
     */
    public const string REQUIREMENT = 'pages|images|posts|sections|tags|authors';

    public function resourceType(): ResourceType
    {
        return match ($this) {
            self::Pages => ResourceType::Page,
            self::Images => ResourceType::Image,
            self::Posts => ResourceType::Post,
            self::Sections, self::Tags => ResourceType::Term,
            self::Authors => ResourceType::Author,
        };
    }

    public function redirectType(): RedirectResourceType
    {
        return match ($this) {
            self::Pages => RedirectResourceType::Page,
            self::Images => RedirectResourceType::Image,
            self::Posts => RedirectResourceType::Post,
            self::Sections, self::Tags => RedirectResourceType::Term,
            self::Authors => RedirectResourceType::Author,
        };
    }

    /**
     * The route of the editor of the resource, with its parameters.
     *
     * @return array{string, array<string, string>}
     */
    public function editor(string $id): array
    {
        return match ($this) {
            self::Sections, self::Tags => ['cms_terms_edit', ['taxonomy' => $this->value, 'id' => $id]],
            default => [\sprintf('cms_%s_edit', $this->value), ['id' => $id]],
        };
    }

    /**
     * The texts of the page, by their key under cms.relations.{kind}.
     */
    public function key(string $key): string
    {
        return \sprintf('cms.relations.%s.%s', match ($this) {
            self::Pages => 'page',
            self::Images => 'image',
            self::Posts => 'post',
            self::Sections => 'section',
            self::Tags => 'tag',
            self::Authors => 'author',
        }, $key);
    }
}
