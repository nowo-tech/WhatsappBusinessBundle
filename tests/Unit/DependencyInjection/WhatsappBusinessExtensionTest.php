<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\DependencyInjection;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Config\TransportKind;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\EventSubscriber\ResetMockTransportsOnRequestSubscriber;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Tests\Support\HostTransport;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(WhatsappBusinessExtension::class)]
final class WhatsappBusinessExtensionTest extends TestCase
{
    public function testLoadDefaultMockProfile(): void
    {
        $container = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([], $container);

        self::assertTrue($container->hasDefinition('nowo_whatsapp_business.transport.default'));
        self::assertSame(MockTransport::class, $container->getDefinition('nowo_whatsapp_business.transport.default')->getClass());
        self::assertTrue($container->getDefinition('nowo_whatsapp_business.transport.default')->hasTag('kernel.reset'));
        self::assertTrue($container->hasAlias(WhatsappClientInterface::class));
        self::assertTrue($container->hasAlias(WhatsappClient::class));
        self::assertTrue($container->hasAlias(WhatsappClientRegistryInterface::class));
        self::assertSame(WhatsappClientRegistry::class, $container->getDefinition('nowo_whatsapp_business.registry')->getClass());
        self::assertSame('default', $container->getParameter('nowo_whatsapp_business.default_profile'));
        self::assertSame(['default'], $container->getParameter('nowo_whatsapp_business.profiles'));
    }

    public function testLoadCloudProfileAndEmptyOptionals(): void
    {
        $container = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'prod',
            'profiles'        => [
                'prod' => [
                    'transport'                    => 'cloud',
                    'phone_number_id'              => '111',
                    'access_token'                 => 'tok',
                    'whatsapp_business_account_id' => '',
                    'app_secret'                   => '',
                    'webhook_verify_token'         => '',
                    'allow_unsigned_webhooks'      => false,
                ],
            ],
        ]], $container);

        self::assertSame(CloudApiProfile::class, $container->getDefinition('nowo_whatsapp_business.profile.prod')->getClass());
        self::assertFalse($container->getDefinition('nowo_whatsapp_business.profile.prod')->isPublic());
        self::assertFalse($container->getDefinition('nowo_whatsapp_business.graph.prod')->isPublic());
        self::assertFalse($container->getDefinition('nowo_whatsapp_business.transport.prod')->isPublic());
        $args = $container->getDefinition('nowo_whatsapp_business.profile.prod')->getArguments();
        self::assertNull($args['$whatsappBusinessAccountId']);
        self::assertNull($args['$appSecret']);
        self::assertNull($args['$webhookVerifyToken']);
        self::assertFalse($container->getDefinition('nowo_whatsapp_business.client.prod')->isPublic());
        self::assertSame(GraphApiClient::class, $container->getDefinition('nowo_whatsapp_business.graph.prod')->getClass());
        self::assertSame(CloudApiTransport::class, $container->getDefinition('nowo_whatsapp_business.transport.prod')->getClass());
        self::assertSame('nowo_whatsapp_business', (new WhatsappBusinessExtension())->getAlias());
    }

    public function testUnknownDefaultProfile(): void
    {
        $this->expectException(UnknownProfileException::class);
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'missing',
            'profiles'        => [
                'default' => [
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                ],
            ],
        ]], new ContainerBuilder());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function refusedKernels(): iterable
    {
        yield 'prod, debug off' => ['prod', false];
        yield 'staging, debug off' => ['staging', false];
        yield 'preprod, debug off' => ['preprod', false];
        yield 'prod, debug on' => ['prod', true];
    }

    #[DataProvider('refusedKernels')]
    public function testUnsignedWebhooksWithoutAppSecretAreRefusedUnlessDebug(string $environment, bool $debug): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('profiles.public.allow_unsigned_webhooks cannot be true without app_secret');
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'public',
            'profiles'        => [
                'public' => [
                    'phone_number_id'         => '111',
                    'access_token'            => 'REDACTED-TEST',
                    'allow_unsigned_webhooks' => true,
                ],
            ],
        ]], $this->kernelContainer($environment, $debug));
    }

    public function testUnsignedWebhooksStayAllowedInDebugOrWithAppSecret(): void
    {
        $dev = $this->kernelContainer('dev', true);
        (new WhatsappBusinessExtension())->load([[
            'profiles' => ['default' => ['phone_number_id' => '111', 'access_token' => 'REDACTED-TEST', 'allow_unsigned_webhooks' => true]],
        ]], $dev);
        self::assertTrue($dev->hasDefinition('nowo_whatsapp_business.profile.default'));

        $prod = $this->kernelContainer('prod', false);
        (new WhatsappBusinessExtension())->load([[
            'profiles' => ['default' => [
                'phone_number_id'         => '111',
                'access_token'            => 'REDACTED-TEST',
                'app_secret'              => 'REDACTED-TEST',
                'allow_unsigned_webhooks' => true,
            ]],
        ]], $prod);
        self::assertTrue($prod->hasDefinition('nowo_whatsapp_business.profile.default'));
    }

    public function testAllowUnsignedWebhooksMustBeALiteralBoolean(): void
    {
        $container = $this->kernelContainer('prod', false);
        $container->registerExtension(new WhatsappBusinessExtension());
        $placeholder = $container->getParameterBag()->get('env(bool:WHATSAPP_ALLOW_UNSIGNED)');
        self::assertIsString($placeholder);
        $container->loadFromExtension('nowo_whatsapp_business', [
            'profiles' => ['default' => [
                'phone_number_id'         => '111',
                'access_token'            => 'REDACTED-TEST',
                'app_secret'              => 'REDACTED-TEST',
                'allow_unsigned_webhooks' => $placeholder,
            ]],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('allow_unsigned_webhooks must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function effectiveUnsignedFlag(): iterable
    {
        yield 'dev with debug keeps the flag' => ['dev', true, true];
        yield 'prod without debug forces false' => ['prod', false, false];
        yield 'staging without debug forces false' => ['staging', false, false];
        yield 'prod with debug forces false' => ['prod', true, false];
    }

    /**
     * L3 (audit BU): with an %env()% app_secret the container compiles, but the effective flag is decided at
     * compile time, so an empty secret at runtime still rejects unsigned webhooks outside debug kernels.
     */
    #[DataProvider('effectiveUnsignedFlag')]
    public function testUnsignedFlagIsForcedFalseOutsideDebugEvenWithEnvSecret(string $environment, bool $debug, bool $expected): void
    {
        $container = $this->kernelContainer($environment, $debug);
        (new WhatsappBusinessExtension())->load([[
            'profiles' => ['default' => [
                'phone_number_id'         => '111',
                'access_token'            => 'REDACTED-TEST',
                'app_secret'              => '%env(WHATSAPP_APP_SECRET)%',
                'allow_unsigned_webhooks' => true,
            ]],
        ]], $container);

        self::assertSame($expected, $container->getDefinition('nowo_whatsapp_business.profile.default')->getArgument('$allowUnsignedWebhooks'));
    }

    public function testUnsignedFlagIsFalseWithoutKernelParameters(): void
    {
        $container = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([[
            'profiles' => ['default' => ['phone_number_id' => '111', 'access_token' => 'REDACTED-TEST', 'allow_unsigned_webhooks' => true]],
        ]], $container);

        self::assertFalse($container->getDefinition('nowo_whatsapp_business.profile.default')->getArgument('$allowUnsignedWebhooks'));
    }

    private function kernelContainer(string $environment, bool $debug): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        $container->setParameter('kernel.debug', $debug);

        return $container;
    }

    public function testCustomProfileAliasesTheHostTransport(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.host_transport', HostTransport::class);
        (new WhatsappBusinessExtension())->load([[
            'profiles' => [
                'default' => [
                    'phone_number_id'   => '111',
                    'access_token'      => 'REDACTED-TEST',
                    'transport_service' => 'app.host_transport',
                ],
            ],
        ]], $container);

        self::assertFalse($container->hasDefinition('nowo_whatsapp_business.transport.default'));
        self::assertTrue($container->hasAlias('nowo_whatsapp_business.transport.default'));
        $alias = $container->getAlias('nowo_whatsapp_business.transport.default');
        self::assertSame('app.host_transport', (string) $alias);
        self::assertFalse($alias->isPublic());
        // No Graph client is built for a host transport, and nothing resets it.
        self::assertFalse($container->hasDefinition('nowo_whatsapp_business.graph.default'));
        self::assertFalse($container->hasDefinition(ResetMockTransportsOnRequestSubscriber::class));
        self::assertSame(TransportKind::Custom, $container->getDefinition('nowo_whatsapp_business.profile.default')->getArgument('$transport'));
        self::assertSame(
            ['default' => 'app.host_transport'],
            $container->getParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER),
        );
    }

    public function testMockAndCustomProfilesCoexistAndOnlyMocksAreReset(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.host_transport', HostTransport::class);
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'demo',
            'profiles'        => [
                'demo' => [
                    'transport'       => 'mock',
                    'phone_number_id' => '111',
                    'access_token'    => 'REDACTED-TEST',
                ],
                'host' => [
                    'transport'         => 'custom',
                    'phone_number_id'   => '222',
                    'access_token'      => 'REDACTED-TEST',
                    'transport_service' => 'app.host_transport',
                ],
            ],
        ]], $container);

        self::assertSame(MockTransport::class, $container->getDefinition('nowo_whatsapp_business.transport.demo')->getClass());
        self::assertTrue($container->hasAlias('nowo_whatsapp_business.transport.host'));
        $resets = $container->getDefinition(ResetMockTransportsOnRequestSubscriber::class)->getArgument('$transports');
        self::assertInstanceOf(IteratorArgument::class, $resets);
        self::assertCount(1, $resets->getValues());
        self::assertSame(['default' => 'demo', 'profiles' => ['demo', 'host']], [
            'default'  => $container->getParameter('nowo_whatsapp_business.default_profile'),
            'profiles' => $container->getParameter('nowo_whatsapp_business.profiles'),
        ]);
        self::assertSame(['host' => 'app.host_transport'], $container->getParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }

    public function testCustomTransportCannotPointAtTheBundleTransportIdOfItsOwnProfile(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('transport_service cannot be "nowo_whatsapp_business.transport.default"');
        (new WhatsappBusinessExtension())->load([[
            'profiles' => [
                'default' => [
                    'phone_number_id'   => '111',
                    'access_token'      => 'REDACTED-TEST',
                    'transport_service' => 'nowo_whatsapp_business.transport.default',
                ],
            ],
        ]], new ContainerBuilder());
    }

    public function testNoTransportServicesParameterWithoutCustomProfiles(): void
    {
        $container = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([], $container);

        self::assertFalse($container->hasParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }
}
