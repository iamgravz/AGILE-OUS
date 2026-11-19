<?php

declare(strict_types=1);

use App\Services\CmsService;
use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class ContentRulesTest extends TestCase
{
    public function testAllowedTransitionsMatchSectionEight(): void
    {
        $allowed = [
            ['draft', 'for_approval'],
            ['for_approval', 'published'],
            ['for_approval', 'returned'],
            ['returned', 'for_approval'],
            ['published', 'archived'],
        ];

        foreach (CmsService::STATUSES as $from) {
            foreach (CmsService::STATUSES as $to) {
                $this->assertSame(
                    in_array([$from, $to], $allowed, true),
                    CmsService::canTransition($from, $to),
                    "{$from} to {$to}"
                );
            }
        }
    }

    public function testSanitizerKeepsTheAllowList(): void
    {
        $id = str_repeat('a', 32);
        $html = '<h2>A</h2><h3>B</h3><h4>C</h4><p><strong>D</strong> <em>E</em> <a href="https://example.com/x">F</a></p>'
            . '<ul><li>G</li></ul><ol><li>H</li></ol><blockquote>I</blockquote>'
            . "<p><img src=\"/media/{$id}\" alt=\"J\"></p>";
        $clean = (new HtmlSanitizer())->clean($html);

        foreach (['<h2>A', '<h3>B', '<h4>C', '<strong>D', '<em>E', 'href="https://example.com/x"', '<ul>', '<ol>', '<li>G', '<blockquote>', "src=\"/media/{$id}\"", 'alt="J"'] as $kept) {
            $this->assertStringContainsString($kept, $clean);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function hostileHtml(): array
    {
        return [
            'script tag' => ['<p>x</p><script>alert(1)</script>', 'alert(1)'],
            'script pasted as text' => ['<p>x&lt;script&gt;alert(1)&lt;/script&gt;</p>', 'alert(1)'],
            'event handler' => ['<p onclick="alert(1)">x</p>', 'onclick'],
            'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'data link' => ['<a href="data:text/html,x">x</a>', 'data:'],
            'iframe' => ['<iframe src="https://example.com"></iframe>', 'iframe'],
            'style attribute' => ['<p style="position:fixed">x</p>', 'style'],
            'style tag' => ['<style>p{}</style>', '<style'],
            'h1' => ['<h1>x</h1>', '<h1'],
            'class' => ['<p class="ql-align-center">x</p>', 'class'],
            'external image' => ['<img src="https://example.com/x.png" alt="x">', 'example.com'],
            'image with script source' => ['<img src="javascript:alert(1)" alt="x">', 'javascript:'],
            'image with handler' => ['<img src="/media/' . 'b' . '" onerror="alert(1)">', 'onerror'],
            'image outside media' => ['<img src="/storage/uploads/x.png" alt="x">', 'storage'],
            'svg' => ['<svg onload="alert(1)"></svg>', 'svg'],
            'form' => ['<form action="/x"><input name="a"></form>', '<input'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostileHtml')]
    public function testSanitizerRemovesHostileHtml(string $html, string $unwanted): void
    {
        $this->assertStringNotContainsStringIgnoringCase($unwanted, (new HtmlSanitizer())->clean($html));
    }
}
