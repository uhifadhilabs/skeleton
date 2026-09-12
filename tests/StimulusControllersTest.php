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
use Symfony\UX\StimulusBundle\AssetMapper\ControllersMapGenerator;

/**
 * THE CORE'S FRONT-END BEHAVIOUR IS ON. Every identifier the core's markup asks
 * for is registered, and each one resolves into the core's own package —
 * assets/controllers.json enabling `@uhifadhi/uhifadhi` is what does it, so
 * this installation ships no file of its own per controller.
 *
 * The identifiers are the contract: a template says
 * `data-controller="uhifadhi--shell-bundle--theme"`, and a control whose
 * identifier is not in this map is a control with no behaviour behind it — a
 * failure that shows up as a dead button, never as an exception.
 */
final class StimulusControllersTest extends KernelTestCase
{
    /**
     * Every controller the core's root assets/package.json declares.
     *
     * @return iterable<string, array{string}>
     */
    public static function coreControllers(): iterable
    {
        yield 'the theme switch' => ['uhifadhi--shell-bundle--theme'];
        yield 'the sidebar' => ['uhifadhi--shell-bundle--sidebar'];
        yield 'the sidebar tree' => ['uhifadhi--shell-bundle--sidebar-tree'];
        yield 'local time' => ['uhifadhi--shell-bundle--localtime'];
        yield 'the permission matrix' => ['uhifadhi--team-bundle--permission-group'];
        yield 'departments' => ['uhifadhi--team-bundle--department'];
        yield 'the area register' => ['uhifadhi--area-bundle--area-register'];
        yield 'an area upload' => ['uhifadhi--area-bundle--area-upload'];
        yield 'the map plate' => ['uhifadhi--atlas-bundle--map-plate'];
        yield 'the boundary presets' => ['uhifadhi--area-bundle--area-presets'];
        yield 'the module order' => ['uhifadhi--area-bundle--module-order'];
    }

    #[DataProvider('coreControllers')]
    public function testTheCoreControllerIsRegistered(string $identifier): void
    {
        self::assertArrayHasKey($identifier, self::controllersMap());
    }

    /**
     * AND IT COMES FROM THE PACKAGE, not from a file in this repository. One
     * re-export per controller in assets/controllers/ used to be what enabled
     * these; it is a list somebody has to remember to extend every time the
     * core grows a control.
     */
    #[DataProvider('coreControllers')]
    public function testTheCoreControllerResolvesIntoTheCoresOwnPackage(string $identifier): void
    {
        $map = self::controllersMap();
        self::assertArrayHasKey($identifier, $map);

        self::assertStringContainsString(
            '/vendor/uhifadhi/uhifadhi/',
            $map[$identifier]->asset->sourcePath,
        );
    }

    /**
     * @return array<string, \Symfony\UX\StimulusBundle\AssetMapper\MappedControllerAsset>
     */
    private static function controllersMap(): array
    {
        self::bootKernel();

        $generator = self::getContainer()->get('stimulus.asset_mapper.controllers_map_generator');
        self::assertInstanceOf(ControllersMapGenerator::class, $generator);

        return $generator->getControllersMap();
    }
}
