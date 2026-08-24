<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

#[CoversNothing]
final class BundleBootTest extends TestCase
{
    public function testKernelBootsAndSendsViaMock(): void
    {
        $kernel = new class('test', true) extends Kernel {
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new WhatsappBusinessBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', [
                        'secret'                => 'test',
                        'http_method_override'  => false,
                        'handle_all_throwables' => true,
                        'php_errors'            => ['log' => true],
                        'http_client'           => ['enabled' => true],
                    ]);
                    $container->loadFromExtension('nowo_whatsapp_business', []);
                });
            }

            public function getCacheDir(): string
            {
                return sys_get_temp_dir() . '/whatsapp-business-bundle-test/' . spl_object_id($this);
            }

            public function getLogDir(): string
            {
                return $this->getCacheDir() . '/log';
            }
        };

        $kernel->boot();
        $client = $kernel->getContainer()->get('nowo_whatsapp_business.client');
        self::assertInstanceOf(WhatsappClientInterface::class, $client);
        $result = $client->sendText('34611111111', 'hello from integration');
        self::assertStringStartsWith('mock.', $result->messageId);
        $kernel->shutdown();
    }
}
