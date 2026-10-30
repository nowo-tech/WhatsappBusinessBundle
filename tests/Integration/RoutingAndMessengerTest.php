<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration;

use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookController;
use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\RouterInterface;

/**
 * Boots a host-like kernel: attribute routes through the BC routing.yaml import (REQ-SF-004)
 * and the #[AsMessageHandler] handler registered exactly once (REQ-MSG-001).
 */
#[CoversNothing]
final class RoutingAndMessengerTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof Kernel) {
            $cacheDir = $this->kernel->getCacheDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove($cacheDir);
        }
        $this->kernel = null;
    }

    public function testAttributeRoutesAreImportedThroughRoutingYaml(): void
    {
        $router = $this->bootKernel()->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        $default = $routes->get('nowo_whatsapp_business_webhook');
        self::assertNotNull($default);
        self::assertSame('/whatsapp/webhook', $default->getPath());
        self::assertSame(['GET', 'POST'], $default->getMethods());
        self::assertNull($default->getDefault('profile'));
        self::assertStringStartsWith(WebhookController::class, (string) $default->getDefault('_controller'));

        $profile = $routes->get('nowo_whatsapp_business_webhook_profile');
        self::assertNotNull($profile);
        self::assertSame('/whatsapp/webhook/{profile}', $profile->getPath());
        self::assertSame(['GET', 'POST'], $profile->getMethods());
        self::assertSame('[a-zA-Z0-9_-]+', $profile->getRequirement('profile'));

        self::assertSame('/whatsapp/webhook/support', $router->generate('nowo_whatsapp_business_webhook_profile', ['profile' => 'support']));
    }

    public function testSendOutgoingMessageIsHandledOnceBySyncBus(): void
    {
        $container = $this->bootKernel()->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $bus = $container->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $envelope = $bus->dispatch(new SendOutgoingMessage(
            new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('queued hello')),
        ));

        $handled = $envelope->all(HandledStamp::class);
        self::assertCount(1, $handled);
        $result = $handled[0]->getResult();
        self::assertInstanceOf(MessageResult::class, $result);
        self::assertStringStartsWith('mock.', $result->messageId);
    }

    private function bootKernel(): Kernel
    {
        $this->kernel = new class('test', true) extends Kernel {
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new WhatsappBusinessBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', [
                        'secret'                => 'test',
                        'test'                  => true,
                        'http_method_override'  => false,
                        'handle_all_throwables' => true,
                        'php_errors'            => ['log' => true],
                        'http_client'           => ['enabled' => true],
                        'messenger'             => ['enabled' => true],
                        'router'                => [
                            'resource' => __DIR__ . '/Fixtures/routes.yaml',
                            'utf8'     => true,
                        ],
                    ]);
                    $container->loadFromExtension('nowo_whatsapp_business', []);
                });
            }

            public function getCacheDir(): string
            {
                return sys_get_temp_dir() . '/whatsapp-business-bundle-routing-test/' . spl_object_id($this);
            }

            public function getLogDir(): string
            {
                return $this->getCacheDir() . '/log';
            }
        };
        $this->kernel->boot();

        return $this->kernel;
    }
}
