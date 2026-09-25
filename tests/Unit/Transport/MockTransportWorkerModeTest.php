<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Transport;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\EventSubscriber\ResetMockTransportsOnRequestSubscriber;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Same transport / client instances across consecutive requests, services_resetter never runs.
 */
#[CoversClass(MockTransport::class)]
#[CoversClass(ResetMockTransportsOnRequestSubscriber::class)]
#[CoversClass(WhatsappBusinessExtension::class)]
final class MockTransportWorkerModeTest extends TestCase
{
    public function testSendsFromOneRequestAreNotVisibleInTheNext(): void
    {
        $transport  = new MockTransport(ProfileFactory::mock());
        $client     = new WhatsappClient(ProfileFactory::mock(), $transport);
        $subscriber = new ResetMockTransportsOnRequestSubscriber([$transport]);

        // Request 1 (user A)
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        $client->sendText('34600000001', 'secret for A');
        $client->markAsRead('wamid.A');
        self::assertCount(2, $transport->recorded());

        // A sub-request keeps the main request's records.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));
        self::assertCount(2, $transport->recorded());

        // Request 2 (user B), no reset() from the framework.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        self::assertSame([], $transport->recorded());

        $client->sendText('34600000002', 'for B');
        $recorded = $transport->recorded();
        self::assertCount(1, $recorded);
        self::assertSame('34600000002', $recorded[0]['to']);
    }

    public function testRecordedSendsAreBounded(): void
    {
        $transport = new MockTransport(ProfileFactory::mock(), 3);
        for ($i = 1; $i <= 5; ++$i) {
            $transport->send(['to' => (string) $i]);
        }

        self::assertSame(['3', '4', '5'], array_column($transport->recorded(), 'to'));

        $default = new MockTransport(ProfileFactory::mock());
        for ($i = 0; $i < MockTransport::DEFAULT_MAX_RECORDED + 10; ++$i) {
            $default->markAsRead('wamid.' . $i);
        }
        self::assertCount(MockTransport::DEFAULT_MAX_RECORDED, $default->recorded());

        $minimum = new MockTransport(ProfileFactory::mock(), 0);
        $minimum->markAsRead('a');
        $minimum->markAsRead('b');
        self::assertSame([['read' => 'b']], $minimum->recorded());
    }

    public function testSubscriberListensEarlyOnKernelRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 4096]],
            ResetMockTransportsOnRequestSubscriber::getSubscribedEvents(),
        );
    }

    public function testExtensionRegistersSubscriberForMockProfilesOnly(): void
    {
        $container = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'demo',
            'profiles'        => [
                'demo' => ['transport' => 'mock', 'phone_number_id' => 'd', 'access_token' => 'd'],
                'prod' => ['transport' => 'cloud', 'phone_number_id' => '1', 'access_token' => 't'],
            ],
        ]], $container);

        $definition = $container->getDefinition(ResetMockTransportsOnRequestSubscriber::class);
        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
        $argument = $definition->getArgument('$transports');
        self::assertInstanceOf(IteratorArgument::class, $argument);

        $values = $argument->getValues();
        self::assertCount(1, $values);
        self::assertInstanceOf(Reference::class, $values[0]);
        self::assertSame('nowo_whatsapp_business.transport.demo', (string) $values[0]);
        self::assertSame(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $values[0]->getInvalidBehavior());

        $cloudOnly = new ContainerBuilder();
        (new WhatsappBusinessExtension())->load([[
            'default_profile' => 'prod',
            'profiles'        => ['prod' => ['transport' => 'cloud', 'phone_number_id' => '1', 'access_token' => 't']],
        ]], $cloudOnly);
        self::assertFalse($cloudOnly->hasDefinition(ResetMockTransportsOnRequestSubscriber::class));
    }

    private function requestEvent(int $type): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }
}
