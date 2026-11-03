<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\AST\ExpressionWithReturnType;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * "MATCH_AGAINST" "(" StateFieldPathExpression "," StringPrimary ")".
 *
 * Full-text relevance of a FULLTEXT-indexed column in MySQL/MariaDB boolean
 * mode: 0 when the row doesn't match, a positive score otherwise.
 */
final class MatchAgainstFunction extends FunctionNode implements ExpressionWithReturnType
{
    public const string NAME = 'MATCH_AGAINST';

    private PathExpression $column;

    private Node $query;

    #[\Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->StateFieldPathExpression();
        $parser->match(TokenType::T_COMMA);
        $this->query = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[\Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            'MATCH (%s) AGAINST (%s IN BOOLEAN MODE)',
            $this->column->dispatch($sqlWalker),
            $this->query->dispatch($sqlWalker),
        );
    }

    #[\Override]
    public function getReturnTypeName(): string
    {
        return Types::FLOAT;
    }
}
