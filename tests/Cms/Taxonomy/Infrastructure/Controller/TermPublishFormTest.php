<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Application\PublicationOutlook;
use Cms\Shared\Infrastructure\Publication\PublicationForm;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Cms\Shared\Infrastructure\Publication\ReplacedRedirectNotice;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Infrastructure\Controller\TaxonomySegment;
use Cms\Taxonomy\Infrastructure\Controller\TermPublishForm;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * Where publishing a section or a tag is confirmed. A section that changes
 * its slug warns of its posts on the site left at the address of now, until
 * each is published again; with none left, it warns of nothing. What the
 * form asks is told by PublicationFormTest. The texts are their keys.
 */
final class TermPublishFormTest extends TestCase
{
    public function testItWarnsOfThePostsOfTheSectionLeftAtItsAddressOfNow(): void
    {
        PageComponents::one($this->compose(3), 'Shared:Alert', [
            'title' => 'cms.sections.publish.posts_left_behind.title',
            'message' => 'cms.sections.publish.posts_left_behind.message(%count%=3, %slug%=antes)',
        ]);
        self::assertSame([], PageComponents::find($this->compose(0), 'Shared:Alert', []));
    }

    private function compose(int $leftBehind): Page
    {
        $translator = new CatalogueTranslator();
        $urlGenerator = new RouteNameUrlGenerator();
        $redirectNames = new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $translator);

        $page = new TermPublishForm($urlGenerator, $translator, new PublicationForm(
            $translator,
            new RedirectTargetField($translator, $urlGenerator),
            new ReplacedRedirectNotice($redirectNames, $translator),
            $redirectNames,
        ))(
            TaxonomySegment::Sections,
            TermResponse::fromTerm(TermMother::published(slug: 'ahora', publishedSlug: 'antes')),
            new PublicationOutlook('/antes/', null, '/ahora/', null, true, 'f1ng3rpr1nt'),
            [],
            $leftBehind,
        );
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
