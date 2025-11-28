<?php declare(strict_types=1);

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\AST\ASTException;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Right function that truncates a field from the database from the right.
 *
 * RightFunction ::= "RIGHT" "(" ArithmeticPrimary "," ArithmeticPrimary ")"
 */
class RightFunction extends FunctionNode
{
    protected ?Node $fieldExpression = null;
    protected ?Node $lengthExpression = null;

    /**
     * @throws ASTException
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf('RIGHT(%s, %s)',
                       $this->fieldExpression->dispatch($sqlWalker),
                       $this->lengthExpression->dispatch($sqlWalker));
    }

    /**
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->fieldExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->lengthExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
