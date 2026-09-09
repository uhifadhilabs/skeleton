<?php

declare(strict_types=1);

/*
 * This file is part of uhifadhi.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;

/**
 * A fresh installation boots, has the core on it, and can address the screens
 * the core draws. Everything past this arrives as a module and is tested there.
 */
final class SmokeTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function coreBundles(): iterable
    {
        yield 'the registry' => [RegistryBundle::class];
        yield 'the shell' => [ShellBundle::class];
        yield 'the atlas' => [AtlasBundle::class];
        yield 'the team' => [TeamBundle::class];
        yield 'the areas' => [AreaBundle::class];
    }

    /**
     * The addresses config/routes/ mounts. `debug:router` is where somebody
     * looks for these, so the router is what is asked.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function mountedRoutes(): iterable
    {
        yield 'the welcome page' => ['welcome', '/'];
        yield 'the sign-in screen' => ['team_login', '/login'];
        yield 'the area register' => ['area_index', '/areas'];
        yield 'an area\'s module grid' => ['seam_area_modules', '/areas/{uuid}/modules'];
    }

    public function testTheKernelBoots(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue(self::getContainer()->has('router'));
    }

    /**
     * The container compiles with the whole core on it. Asked bundle by bundle,
     * so a line missing from config/bundles.php fails here and names itself
     * rather than surfacing as a blank page.
     *
     * @param class-string $class
     */
    #[DataProvider('coreBundles')]
    public function testTheCoreIsInstalled(string $class): void
    {
        $bundles = self::bootKernel()->getBundles();

        self::assertArrayHasKey((new \ReflectionClass($class))->getShortName(), $bundles);
    }

    #[DataProvider('mountedRoutes')]
    public function testTheCoreScreensAreMounted(string $name, string $path): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $route = $router->getRouteCollection()->get($name);
        self::assertNotNull($route, $name.' is mounted.');
        self::assertSame($path, $route->getPath());
    }
}
