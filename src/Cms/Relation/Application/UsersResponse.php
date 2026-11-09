<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

/**
 * One page of the resources that use another, and whether more follow.
 */
final readonly class UsersResponse
{
    /**
     * @param list<UserResponse> $users
     */
    public function __construct(
        public array $users,
        public bool $hasMore,
    ) {}
}
