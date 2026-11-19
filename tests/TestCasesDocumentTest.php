<?php

declare(strict_types=1);

use App\Core\Config;
use PHPUnit\Framework\TestCase;

/** docs/test-cases.md stays honest: every case is present, and every test it names really exists. */
final class TestCasesDocumentTest extends TestCase
{
    private string $document;

    protected function setUp(): void
    {
        $this->document = (string) file_get_contents(Config::basePath('docs/test-cases.md'));
    }

    public function testAllThirtyFiveCasesAreListed(): void
    {
        foreach (range(1, 35) as $number) {
            $this->assertMatchesRegularExpression(sprintf('/^\| TC-%02d \|/m', $number), $this->document);
        }
    }

    public function testEveryNamedTestExists(): void
    {
        preg_match_all('/`(\w+Test)::(test\w+)`/', $this->document, $matches, PREG_SET_ORDER);
        $this->assertGreaterThan(30, count($matches));

        foreach ($matches as [, $class, $method]) {
            $source = (string) file_get_contents(Config::basePath("tests/{$class}.php"));
            $this->assertStringContainsString("function {$method}(", $source, "{$class}::{$method}");
        }
    }

    public function testEveryBusinessRuleAskedForHasATest(): void
    {
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 17, 18, 19, 20, 21] as $rule) {
            $this->assertMatchesRegularExpression(sprintf('/^\| BR-%02d \|.*`\w+Test::test/m', $rule), $this->document);
        }
    }
}
