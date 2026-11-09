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
    ) {}

    /**
     * @param list<UserResponse> $users
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $users): array
    {
        $ids = static fn (ResourceType $type): array => array_values(array_map(
            static fn (UserResponse $user): string => $user->id,
            array_filter($users, static fn (UserResponse $user): bool => $type->value === $user->type),
        ));

        return [
            ...($this->pageUserRows)($ids(ResourceType::Page)),
            ...($this->imageUserRows)($ids(ResourceType::Image)),
            ...($this->settingsUserRows)($ids(ResourceType::Settings)),
        ];
    }
}
