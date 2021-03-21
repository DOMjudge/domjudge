<?php declare(strict_types=1);

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;

/**
 * Class RightFunction
 *
 * Right function that truncates a field from the database from the right
 *
 * RightFunction ::= "RIGHT" "(" ArithmeticPrimary "," ArithmeticPrimary ")"
 *
 * @package App\Doctrine\ORM\Query\AST\Functions
 */
class RightFunction extends FunctionNode
{
    /**
     * @var Node|null
     */
    protected $fieldExpression = null;

    /**
     * @var Node|null
     */
    protected $lengthExpression = null;

    /**
     * @inheritdoc
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return sprintf('RIGHT(%s, %s)',
                       $this->fieldExpression->dispatch($sqlWalker),
                       $this->lengthExpression->dispatch($sqlWalker));
    }

    /**
     * @inheritdoc
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->fieldExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->lengthExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
