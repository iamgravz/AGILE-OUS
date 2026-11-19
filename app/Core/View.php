<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;
use RuntimeException;
use Throwable;

/**
 * Renders PHP templates from app/Views. Templates run with $this set to the View, so they can use
 * $this->start('title') ... $this->end(), $this->section('title') and $this->partial('partials/x').
 */
final class View
{
    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $open = [];

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $instance = new self();
        $content = $instance->renderFile($view, $data);

        if ($layout === null) {
            return $content;
        }

        $instance->sections['content'] = $content;

        return $instance->renderFile($layout, $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $view, array $data = []): string
    {
        return $this->renderFile($view, $data);
    }

    public function start(string $name): void
    {
        $this->open[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->open) ?? throw new LogicException('View section ended without a start.');
        $this->sections[$name] = trim((string) ob_get_clean());
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** @param array<string, mixed> $__data */
    private function renderFile(string $__view, array $__data): string
    {
        $__path = Config::basePath("app/Views/{$__view}.php");

        if (!is_file($__path)) {
            throw new RuntimeException("View not found: {$__view}");
        }

        extract($__data, EXTR_SKIP);
        $__level = ob_get_level();
        ob_start();

        try {
            require $__path;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $__level) {
                ob_end_clean();
            }
            throw $exception;
        }
    }
}
