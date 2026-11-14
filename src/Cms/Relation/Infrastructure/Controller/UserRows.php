<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Relation\Application\UserResponse;
use Cms\Relation\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * The resources that use another, as rows like those of their own lists,
 * each leading to where it is edited: pages first, then images, then the
 * settings. A resource gone meanwhile is left out.
 */
final readonly class UserRows
{
    public function __construct(
        private PageUserRows $pageUserRows,
        private ImageUserRows $imageUserRows,
        private SettingsUserRows $settingsUserRows,
        private AuthorUserRows $authorUserRows,
    ) {}

    /**
     * @param list<UserResponse> $users
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $users): array
    {
        $ids = [ResourceType::Page->value => [], ResourceType::Image->value => [], ResourceType::Settings->value => [], ResourceType::Author->value => []];

        foreach ($users as $user) {
            $ids[$user->type][] = $user->id;
        }

        return [
            ...($this->pageUserRows)($ids[ResourceType::Page->value]),
            ...($this->imageUserRows)($ids[ResourceType::Image->value]),
            ...($this->settingsUserRows)($ids[ResourceType::Settings->value]),
            ...($this->authorUserRows)($ids[ResourceType::Author->value]),
        ];
    }
}
