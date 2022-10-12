<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Clarification;
use PHPUnit\Framework\TestCase;

class ClarificationTest extends TestCase
{
    public function testSummary(): void
    {
        $clarification = new Clarification();
        $text =
'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod
tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.';
        $clarification->setBody($text);
        $this->assertEquals('Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod …',
            $clarification->getSummary());
    }

    public function testUncutSummary(): void
    {
        $clarification = new Clarification();
        $text = 'Is this a quick question?';
        $clarification->setBody($text);
        $this->assertEquals($text . ' ', $clarification->getSummary());
    }

    public function testIgnoreQuotedText(): void
    {
        $clarification = new Clarification();
        $text =
'> Does P equal NP?

You bet.';
        $clarification->setBody($text);
        $this->assertEquals('You bet. ', $clarification->getSummary());
    }

    public function testMergeNewlines(): void
    {
        $clarification = new Clarification();
        $text =
'First line,
second line,
third line,
fourth line,
fifth line,
sixth line,
seventh line,
eighth line,
and so on.';
        $clarification->setBody($text);
        $this->assertEquals('First line, second line, third line, fourth line, fifth line, sixth line, sevent…',
            $clarification->getSummary());
    }
}
