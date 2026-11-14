<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration;

use LogicException;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Tests\Support\HostTransport;
use Nowo\WhatsappBusinessBundle\Tests\Support\ProfileAwareHostTransport;
use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_key_first;
use function gc_collect_cycles;
use function memory_get_usage;
use function spl_object_id;
use function sys_get_temp_dir;

/**
 * REQ-ARCH-001 rule 5: a host plugs its own TransportInterface through `transport_service`, boots, and sends
 * through it; a service that does not implement the port fails the build.
 */
#[CoversNothing]
final class CustomTransportBootTest extends TestCase
{
    public function testHostTransportServesTheProfile(): void
    {
        $kernel = $this->kernel(HostTransport::class, 'app.host_transport');
        $kernel->boot();
        $container = $kernel->getContainer();

        $client = $container->get('nowo_whatsapp_business.client');
        self::assertInstanceOf(WhatsappClientInterface::class, $client);
        self::assertSame('host.sent', $client->sendText('34611111111', 'through the host transport')->messageId);
        self::assertSame('host.media', $client->uploadMedia('/dev/null', 'image/jpeg')->id);
        // No Graph client is built for a host transport, so the profile needs no HTTP client at all.
        self::assertFalse($container->has('nowo_whatsapp_business.graph.default'));

        $kernel->shutdown();
    }

    /**
     * REQ-RUNTIME-002: one kept-alive kernel, `services_resetter` never called, the host transport serves every
     * send identically and memory stays flat (the bundle keeps nothing per request for a `custom` profile).
     */
    public function testHostTransportSurvivesAKeptAliveKernelWithoutReset(): void
    {
        $kernel = $this->kernel(HostTransport::class, 'app.host_transport');
        $kernel->boot();
        $client = $kernel->getContainer()->get('nowo_whatsapp_business.client');
        self::assertInstanceOf(WhatsappClientInterface::class, $client);

        for ($i = 0; $i < 50; ++$i) {
            $client->sendText('34611111111', 'warm up ' . $i);
        }
        gc_collect_cycles();
        $baseline = memory_get_usage();
        for ($i = 0; $i < 500; ++$i) {
            self::assertSame('host.sent', $client->sendText('34611111111', 'request ' . $i)->messageId);
        }
        gc_collect_cycles();
        self::assertLessThan(1024 * 1024, memory_get_usage() - $baseline);

        $kernel->shutdown();
    }

    public function testServiceThatIsNotATransportFailsTheBuild(): void
    {
        $kernel = $this->kernel(self::class, 'app.not_a_transport');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('transport_service: the service "app.not_a_transport"');
        $kernel->boot();
    }

    /**
     * L-1 (audit PH): a custom profile naming another profile's bundle transport (directly or through a host
     * alias) would send through that profile's token and phone number while reporting its own name. The container
     * refuses to compile.
     */
    public function testACustomProfileCannotBorrowAnotherProfileTransport(): void
    {
        foreach (['nowo_whatsapp_business.transport.b' => null, 'app.alias' => 'nowo_whatsapp_business.transport.b'] as $serviceId => $aliasTarget) {
            try {
                $this->compile(
                    [
                        'a' => ['phone_number_id' => 'pn-a', 'access_token' => 'tok-a', 'transport_service' => $serviceId],
                        'b' => ['phone_number_id' => 'pn-b', 'access_token' => 'tok-b', 'transport' => 'cloud'],
                    ],
                    static function (ContainerBuilder $container) use ($serviceId, $aliasTarget): void {
                        if ($aliasTarget !== null) {
                            $container->setAlias($serviceId, $aliasTarget);
                        }
                    },
                );
                self::fail('The container must not compile with transport_service "' . $serviceId . '".');
            } catch (LogicException $exception) {
                self::assertStringContainsString('nowo_whatsapp_business.profiles.a.transport_service', $exception->getMessage());
                self::assertStringContainsString('a service of this bundle', $exception->getMessage());
            }
        }
    }

    /**
     * I-1 (audit PH): one host service named by two profiles is one shared instance; a
     * ProfileAwareTransportInterface implementation is bound to each profile by name.
     */
    public function testOneProfileAwareHostServiceServesTwoProfiles(): void
    {
        $container = $this->compile(
            [
                'sales'  => ['phone_number_id' => 'pn-s', 'access_token' => 'tok-s', 'transport_service' => 'app.shared'],
                'alerts' => ['phone_number_id' => 'pn-a', 'access_token' => 'tok-a', 'transport_service' => 'app.shared'],
            ],
            static function (ContainerBuilder $container): void {
                $container->register('app.shared', ProfileAwareHostTransport::class);
                $container->setAlias('pub.sales', 'nowo_whatsapp_business.client.sales')->setPublic(true);
                $container->setAlias('pub.alerts', 'nowo_whatsapp_business.client.alerts')->setPublic(true);
            },
        );

        $sales  = $container->get('pub.sales');
        $alerts = $container->get('pub.alerts');
        self::assertInstanceOf(WhatsappClientInterface::class, $sales);
        self::assertInstanceOf(WhatsappClientInterface::class, $alerts);
        self::assertSame('sales.sent', $sales->sendText('34611111111', 'a')->messageId);
        self::assertSame('alerts.sent', $alerts->sendText('34611111111', 'b')->messageId);
    }

    /**
     * Compiles a bare container with the bundle and the given profiles.
     *
     * @param array<string, array<string, mixed>> $profiles
     * @param callable(ContainerBuilder): void $services
     */
    private function compile(array $profiles, callable $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new WhatsappBusinessExtension());
        $container->loadFromExtension('nowo_whatsapp_business', ['profiles' => $profiles, 'default_profile' => array_key_first($profiles)]);
        $container->register('http_client', MockHttpClient::class);
        $container->register('logger', NullLogger::class);
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        $container->setAlias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services($container);
        (new WhatsappBusinessBundle())->build($container);
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass());
        $container->compile(true);

        return $container;
    }

    /**
     * Boots a framework kernel whose only WhatsApp profile is served by $serviceId (class $class).
     */
    private function kernel(string $class, string $serviceId): Kernel
    {
        return new class('test', true, $class, $serviceId) extends Kernel {
            public function __construct(
                string $environment,
                bool $debug,
                private readonly string $transportClass,
                private readonly string $transportServiceId,
            ) {
                parent::__construct($environment, $debug);
            }

            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new WhatsappBusinessBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $class     = $this->transportClass;
                $serviceId = $this->transportServiceId;
                $loader->load(static function (ContainerBuilder $container) use ($class, $serviceId): void {
                    $container->loadFromExtension('framework', [
                        'secret'                => 'test',
                        'http_method_override'  => false,
                        'handle_all_throwables' => true,
                        'php_errors'            => ['log' => true],
                    ]);
                    $container->setDefinition($serviceId, (new Definition($class))->setPublic(false));
                    $container->loadFromExtension('nowo_whatsapp_business', [
                        'profiles' => [
                            'default' => [
                                'phone_number_id'   => '111',
                                'access_token'      => 'REDACTED-TEST',
                                'transport_service' => $serviceId,
                            ],
                        ],
                    ]);
                });
            }

            public function getCacheDir(): string
            {
                return sys_get_temp_dir() . '/whatsapp-business-bundle-custom-transport/' . spl_object_id($this);
            }

            public function getLogDir(): string
            {
                return $this->getCacheDir() . '/log';
            }
        };
    }
}
