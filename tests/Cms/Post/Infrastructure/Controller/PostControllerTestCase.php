<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Doctrine\ORM\EntityManagerInterface;

abstract class PostControllerTestCase extends CmsWebTestCase
{
    protected const string HOST = 'http://cms.blogautista.localhost';

    protected function savePost(Post $post): Post
    {
        self::getContainer()->get(PostRepository::class)->save($post);

        return $post;
    }

    /**
     * Reads the post back from the database, not from Doctrine's identity map.
     */
    protected function storedPost(Post $post): ?Post
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PostRepository::class)->find($post->id());
    }

    /**
     * Saves the section, the author, the tags and the opening image of
     * PostMother: the section, the author and the image on the site or off
     * it, the first tag on the site and the other a draft.
     */
    protected function saveClassifiers(bool $sectionOnSite = true, bool $authorOnSite = true, bool $openingImageOnSite = true): void
    {
        self::getContainer()->get(ImageRepository::class)->save($openingImageOnSite ? ImageMother::published(id: PostMother::OPENING_IMAGE) : ImageMother::create(id: PostMother::OPENING_IMAGE));
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save($sectionOnSite ? TermMother::published(id: PostMother::SECTION) : TermMother::create(id: PostMother::SECTION));
        $terms->save(TermMother::published(id: PostMother::TAG, taxonomy: Taxonomy::Tag, name: 'Sensorialidad', slug: 'sensorialidad'));
        $terms->save(TermMother::create(id: PostMother::OTHER_TAG, taxonomy: Taxonomy::Tag, name: 'Ruido', slug: 'ruido'));
        self::getContainer()->get(AuthorRepository::class)->save($authorOnSite ? AuthorMother::published(id: PostMother::AUTHOR) : AuthorMother::publishable(id: PostMother::AUTHOR));
    }
}
