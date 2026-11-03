<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Console;

use Cms\Page\Application\UniquePageCreator;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Shared\Domain\DomainError;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cms:page:create-unique',
    description: 'Creates one of the pages the site has only once, such as the home or the contact page',
)]
final readonly class UniquePageCreateCommand
{
    public function __construct(private PageRepository $pageRepository) {}

    public function __invoke(
        SymfonyStyle $style,
        #[Argument(description: 'Type of the unique page', suggestedValues: [self::class, 'uniqueTypes'])]
        string $type,
        #[Argument(description: 'Title of the page; the slug is derived from it, except for the home page')]
        string $title,
    ): int {
        try {
            new UniquePageCreator(PageId::random()->value(), $type, $title)($this->pageRepository);
        } catch (DomainError $error) {
            $style->error($error->getMessage());

            return Command::FAILURE;
        }

        $style->success(\sprintf('The "%s" page was created.', $type));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public function uniqueTypes(): array
    {
        return array_map(static fn (PageType $type): string => $type->value, PageType::uniqueTypes());
    }
}
