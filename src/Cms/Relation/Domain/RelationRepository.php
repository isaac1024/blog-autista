<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

/**
 * Outbound port used to persist and read relations.
 */
interface RelationRepository
{
    /**
     * Replaces every relation of the source to resources of the type with
     * relations to those given, all or none.
     *
     * @param list<string> $targetIds
     */
    public function replace(ResourceType $sourceType, string $sourceId, ResourceType $targetType, array $targetIds): void;

    /**
     * Whether any resource uses the one given.
     */
    public function isUsed(ResourceType $targetType, string $targetId): bool;

    /**
     * A slice of the relations of every resource that uses the one given:
     * pages first, then images, then the settings.
     *
     * @return list<Relation>
     */
    public function usersOf(ResourceType $targetType, string $targetId, int $limit, int $offset): array;
}
