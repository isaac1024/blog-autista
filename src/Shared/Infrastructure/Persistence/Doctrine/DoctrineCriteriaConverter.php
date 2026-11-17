<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Filter;
use Shared\Domain\Criteria\FilterOperator;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Applies a domain Criteria to a Doctrine query, mapping the public field
 * names onto the entity's (possibly embedded) properties. Only mapped fields
 * are accepted, so callers can't reach columns they were not meant to.
 *
 * Full-text filters rank the results by relevance first; the criteria order,
 * if any, breaks the ties.
 *
 * A page of results is read in two steps: the ids of the page, which the
 * indexes alone give, then the entities of those ids. Reading the entities
 * whole for the page would read, and throw away, every one before it,
 * contents and all, which takes seconds a few thousand rows in.
 */
final readonly class DoctrineCriteriaConverter
{
    /**
     * Words the full-text index stores: runs of letters and digits at least as
     * long as InnoDB's innodb_ft_min_token_size (3). Shorter words are never
     * indexed, so requiring them would match nothing. Anything else, boolean
     * operators included, separates words.
     */
    private const string WORD = '/[\p{L}\p{N}]{3,}/u';

    /**
     * @param array<string, string> $fields public field name => property path relative to the root entity
     */
    public function __construct(private array $fields) {}

    /**
     * The entities of the class that match the criteria, in its order.
     *
     * @template T of object
     *
     * @param class-string<T> $class whose identifier is a single field
     *
     * @return list<T>
     */
    public function matching(EntityManagerInterface $entityManager, string $class, string $alias, Criteria $criteria): array
    {
        $metadata = $entityManager->getClassMetadata($class);
        $idField = $metadata->getSingleIdentifierFieldName();
        $platform = $entityManager->getConnection()->getDatabasePlatform();
        $idType = Type::getType((string) $metadata->getTypeOfField($idField));
        // An id read alone may come as stored, or as the entity holds it.
        $stored = static function (mixed $id) use ($idType, $platform): string {
            $value = \is_string($id) ? $id : $idType->convertToDatabaseValue($id, $platform);

            return \is_string($value) ? $value : throw new \UnexpectedValueException('An id is stored as no text.');
        };

        $ids = array_map($stored, $this->apply($criteria, $entityManager->createQueryBuilder()->select(\sprintf('%s.%s', $alias, $idField))->from($class, $alias), $alias)
            ->getQuery()
            ->getSingleColumnResult());

        if ([] === $ids) {
            return [];
        }

        /** @var list<T> $entities */
        $entities = $entityManager->createQueryBuilder()
            ->select($alias)
            ->from($class, $alias)
            ->where(\sprintf('%s.%s IN (:ids)', $alias, $idField))
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
        $byId = [];

        foreach ($entities as $entity) {
            $byId[$stored($metadata->getSingleIdReflectionProperty()?->getValue($entity))] = $entity;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?object => $byId[$id] ?? null, $ids)));
    }

    private function apply(Criteria $criteria, QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        $queryBuilder
            ->setFirstResult($criteria->offset)
            ->setMaxResults($criteria->limit);

        foreach ($criteria->filters as $index => $filter) {
            $this->filter($queryBuilder, $alias, $filter, \sprintf('criteria_%d', $index));
        }

        if (null !== $criteria->order) {
            $queryBuilder->addOrderBy(
                $this->field($alias, $criteria->order->field),
                match ($criteria->order->direction) {
                    OrderDirection::Ascending => 'ASC',
                    OrderDirection::Descending => 'DESC',
                },
            );
        }

        return $queryBuilder;
    }

    private function filter(QueryBuilder $queryBuilder, string $alias, Filter $filter, string $parameter): void
    {
        $field = $this->field($alias, $filter->field);

        if (FilterOperator::Matches === $filter->operator) {
            $this->fullText($queryBuilder, $field, (string) $filter->value, $parameter);

            return;
        }

        $expr = $queryBuilder->expr();
        $placeholder = ':'.$parameter;

        $queryBuilder
            ->andWhere(match ($filter->operator) {
                FilterOperator::Equal => $expr->eq($field, $placeholder),
                FilterOperator::NotEqual => $expr->neq($field, $placeholder),
                FilterOperator::GreaterThan => $expr->gt($field, $placeholder),
                FilterOperator::GreaterThanOrEqual => $expr->gte($field, $placeholder),
                FilterOperator::LessThan => $expr->lt($field, $placeholder),
                FilterOperator::LessThanOrEqual => $expr->lte($field, $placeholder),
                FilterOperator::Contains,
                FilterOperator::StartsWith,
                FilterOperator::EndsWith => $expr->like($field, $placeholder),
            })
            ->setParameter($parameter, match ($filter->operator) {
                FilterOperator::Contains => '%'.$this->escapeLike($filter->value).'%',
                FilterOperator::StartsWith => $this->escapeLike($filter->value).'%',
                FilterOperator::EndsWith => '%'.$this->escapeLike($filter->value),
                default => $filter->value,
            });
    }

    /**
     * Requires every word of the text, as a prefix so "famil" also finds
     * "familias", and ranks the rows by relevance. Text without words becomes
     * an empty query, which matches nothing.
     */
    private function fullText(QueryBuilder $queryBuilder, string $field, string $text, string $parameter): void
    {
        preg_match_all(self::WORD, $text, $matches);

        $relevance = \sprintf('%s(%s, :%s)', MatchAgainstFunction::NAME, $field, $parameter);

        $queryBuilder
            ->andWhere(\sprintf('%s > 0', $relevance))
            ->addSelect(\sprintf('%s AS HIDDEN %s_relevance', $relevance, $parameter))
            ->addOrderBy(\sprintf('%s_relevance', $parameter), 'DESC')
            ->setParameter($parameter, implode(' ', array_map(
                static fn (string $word): string => '+'.$word.'*',
                $matches[0],
            )));
    }

    /**
     * Makes LIKE wildcards in the value match literally.
     */
    private function escapeLike(bool|float|int|string $value): string
    {
        return addcslashes((string) $value, '%_\\');
    }

    private function field(string $alias, string $field): string
    {
        return $alias.'.'.($this->fields[$field] ?? throw InvalidCriteria::unknownField($field));
    }
}
