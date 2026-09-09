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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\TeamBundle\Security\ApiTokenAuthenticator;

/**
 * THE ONE RULE, exercised over HTTP: everything is behind sign-in, the few
 * addresses a stranger has to reach are open, and the machine door answers a
 * missing token with 401 rather than a redirect to a form no client can fill in.
 *
 * config/packages/security.yaml is the file under test. What a module lets a
 * signed-in person DO is the module's own rule and is tested with the module.
 */
final class SecurityTest extends WebTestCase
{
    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/areas');

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());
        self::assertStringEndsWith('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheHomepageIsBehindSignInToo(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());
        self::assertStringEndsWith('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheSignInScreenIsOpen(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('name="_password"', (string) $client->getResponse()->getContent());
    }

    public function testTheForgottenPasswordScreenIsOpen(): void
    {
        $client = self::createClient();
        $client->request('GET', '/reset-password');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    /**
     * NOT A REDIRECT. A field client that met one would follow it, get a
     * sign-in page with status 200 and conclude the call succeeded.
     *
     * A bare installation mounts no address under `/api` that asks for a token —
     * the only one the core has is where a client GETS one, and that is open by
     * design — so what an unrouted path answers here is 404. The address is
     * asked anyway, because the failure this guards against is a 302: the day a
     * module mounts an endpoint, the difference between "you are not signed in"
     * and "go and fill in this form" has to already be real.
     */
    public function testNothingUnderTheMachineDoorRedirectsToTheSignInForm(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/anything');

        self::assertNotSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());
    }

    /**
     * AND THE 401 IS THE ENTRY POINT'S, not a redirect the browser firewall
     * would issue. The firewall is asked directly because no shipped route can
     * ask it over HTTP yet.
     */
    public function testTheMachineDoorIsStatelessAndAnswersThroughTheTokenEntryPoint(): void
    {
        self::bootKernel();

        $firewalls = self::getContainer()->getParameter('security.firewalls');
        self::assertIsArray($firewalls);
        self::assertContains('api', $firewalls);

        $context = self::getContainer()->get('security.firewall.map.context.api');
        self::assertInstanceOf(FirewallContext::class, $context);

        $config = $context->getConfig();
        self::assertNotNull($config);
        self::assertTrue($config->isStateless());
        self::assertSame(ApiTokenAuthenticator::class, $config->getEntryPoint());
    }

    /**
     * The authenticator this file names is the TEAM's, and it is reachable —
     * a firewall naming a service id nothing registers is a container that does
     * not compile, so this fails loudly rather than at the first request.
     */
    public function testTheMachineDoorsAuthenticatorIsTheTeamsAndIsRegistered(): void
    {
        self::bootKernel();

        self::assertTrue(self::getContainer()->has(ApiTokenAuthenticator::class));
        self::assertInstanceOf(
            ApiTokenAuthenticator::class,
            self::getContainer()->get(ApiTokenAuthenticator::class),
        );
    }
}
