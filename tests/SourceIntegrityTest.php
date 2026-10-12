<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Structural checks for mistakes php -l cannot see because they only fail on
 * error paths at runtime (missing facade imports, helper methods that do not
 * exist on the calling class, unguarded lookup endpoints).
 */
class SourceIntegrityTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function sourceFiles(): iterable
    {
        $root = realpath(__DIR__ . '/../src');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                yield $relative => [$file->getPathname()];
            }
        }
    }

    /** @dataProvider sourceFiles */
    public function test_log_facade_is_imported_where_used(string $path): void
    {
        $content = file_get_contents($path);
        if (!preg_match('/(?<![\\\\\w])Log::/', $content)) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertStringContainsString('use Illuminate\Support\Facades\Log;', $content, basename($path) . ' calls Log:: without importing the facade');
    }

    /** @dataProvider sourceFiles */
    public function test_get_logger_is_defined_where_called(string $path): void
    {
        $content = file_get_contents($path);
        if (!str_contains($content, '$this->getLogger()')) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertMatchesRegularExpression('/function\s+getLogger\s*\(/', $content, basename($path) . ' calls getLogger() without defining it');
    }

    public function test_device_lookup_requires_admin(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/LookupController.php');

        $this->assertSame(2, substr_count($content, '$this->requireAdmin();'));
    }

    public function test_every_map_read_action_checks_map_access(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/RenderController.php');

        foreach (['json', 'live', 'replay', 'embed', 'export', 'sse'] as $action) {
            $this->assertMatchesRegularExpression(
                '/public function ' . $action . '\(Map \$map[^)]*\)[^{]*\{\s*\$this->authorizeView\(\$map\);/',
                $content,
                "RenderController::{$action}() must call authorizeView() first"
            );
        }
    }

    public function test_index_page_can_return_installer_redirect(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/PageController.php');

        $this->assertStringContainsString('public function index(): View|RedirectResponse', $content);
        $this->assertStringNotContainsString('SHOW TABLES', $content);
    }
}
