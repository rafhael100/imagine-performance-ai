<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BootstrapManifestTest extends TestCase
{
    public function test_composer_declares_laravel_13_and_php_83(): void
    {
        $path = dirname(__DIR__, 2).'/composer.json';
        $config = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('^8.3', $config['require']['php']);
        self::assertSame('^13.0', $config['require']['laravel/framework']);
    }

    public function test_no_web_or_api_routes_are_defined(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root.'/routes/web.php');
        self::assertFileDoesNotExist($root.'/routes/api.php');
    }

    public function test_no_payment_or_ads_packages_are_declared(): void
    {
        $root = dirname(__DIR__, 2);
        $config = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $packages = array_keys($config['require']);

        self::assertSame(['php', 'laravel/framework'], $packages);
    }
}
