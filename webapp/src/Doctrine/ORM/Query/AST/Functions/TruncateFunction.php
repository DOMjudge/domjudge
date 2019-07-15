<?php declare(strict_types=1);

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;

/**
 * Class TruncateFunction
 *
 * Truncate function that truncates a field from the database if too long and adds a truncation message
 *
 * TruncateFunction ::= "TRUNCATE" "(" ArithmeticPrimary "," ArithmeticPrimary "," ArithmeticPrimary ")"
 *
 * @package App\Doctrine\ORM\Query\AST\Functions
 */
class TruncateFunction extends FunctionNode
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
     * @var Node|null
     */
    protected $appendWhenTruncatedExpression = null;

    /**
     * @inheritdoc
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return sprintf('IF(CHAR_LENGTH(%s) > %s, CONCAT(LEFT(%s, %s), %s), %s)',
                       $this->fieldExpression->dispatch($sqlWalker),
                       $this->lengthExpression->dispatch($sqlWalker),
                       $this->fieldExpression->dispatch($sqlWalker),
                       $this->lengthExpression->dispatch($sqlWalker),
                       $this->appendWhenTruncatedExpression->dispatch($sqlWalker),
                       $this->fieldExpression->dispatch($sqlWalker));
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
        $parser->match(Lexer::T_COMMA);
        $this->appendWhenTruncatedExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
