<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration;

use LogicException;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\Tests\Support\HostTransport;
use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

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
