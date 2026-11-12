<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration\Worker;

use ArrayObject;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Event\IncomingMessageEvent;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function gc_collect_cycles;
use function hash_hmac;
use function json_encode;
use function memory_get_usage;
use function sprintf;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-RUNTIME-002: one kept-alive kernel, the services resetter never runs (FrankenPHP worker mode with kernel reset
 * false), N signed webhooks through `http_kernel` handle() + terminate() as two tenants (profiles) in alternation.
 *
 * Each request must verify against its own tenant's secret / verify token, see only its own message, and its reply
 * must be the only send recorded by the mock transport (cleared per main request without `kernel.reset`); memory
 * must stay flat.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1000;

    private const WARM_UP = 50;

    private const MEMORY_MARGIN_BYTES = 4 * 1024 * 1024;

    protected function tearDown(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/whatsapp_business_kept_alive');
    }

    public function testWebhooksInOneKernelWithoutResetterKeepTenantsSeparatedAndMemoryFlat(): void
    {
        $kernel = new KeptAliveKernel('test', false);
        $kernel->boot();
        $container = $kernel->getContainer();
        $locator   = $container->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $locator);

        // Fetched once and kept, as a kept-alive worker does; `services_resetter` is never called.
        $registry   = $locator->get(WhatsappClientRegistryInterface::class);
        $dispatcher = $locator->get('event_dispatcher');
        self::assertInstanceOf(WhatsappClientRegistryInterface::class, $registry);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $tenants = [
            'alpha' => ['phone' => '1000001', 'secret' => 'alpha-secret', 'verify' => 'alpha-verify'],
            'beta'  => ['phone' => '2000002', 'secret' => 'beta-secret', 'verify' => 'beta-verify'],
        ];
        $mocks = [];
        foreach ($tenants as $name => $_) {
            $mock = $locator->get('nowo_whatsapp_business.transport.' . $name);
            self::assertInstanceOf(MockTransport::class, $mock);
            $mocks[$name] = $mock;
        }

        /** @var ArrayObject<int, array{string, string}> $seen */
        $seen = new ArrayObject();
        $dispatcher->addListener(IncomingMessageEvent::class, static function (IncomingMessageEvent $event) use ($seen, $registry): void {
            $seen[] = [$event->profile, (string) $event->message->text];
            $registry->get($event->profile)->sendText($event->message->from, 'reply to ' . $event->message->text);
        });

        $baseline = 0;

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if ($i === self::WARM_UP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            $tenant = $i % 2 === 0 ? 'alpha' : 'beta';
            $other  = $tenant === 'alpha' ? 'beta' : 'alpha';
            $at     = sprintf('iteration %d (%s)', $i, $tenant);
            $seen->exchangeArray([]);

            // GET subscribe challenge: only the tenant's own verify token is accepted.
            $challenge = 'challenge-' . $i;
            $response  = $this->handle($kernel, Request::create('/whatsapp/webhook/' . $tenant, 'GET', [
                'hub_mode'         => 'subscribe',
                'hub_verify_token' => $tenants[$tenant]['verify'],
                'hub_challenge'    => $challenge,
            ]));
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $at);
            self::assertSame($challenge, $response->getContent(), $at);
            $response = $this->handle($kernel, Request::create('/whatsapp/webhook/' . $tenant, 'GET', [
                'hub_mode'         => 'subscribe',
                'hub_verify_token' => $tenants[$other]['verify'],
                'hub_challenge'    => $challenge,
            ]));
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $at);

            // POST signed with the tenant's secret, carrying a message unique to this iteration and tenant.
            $text     = sprintf('hello from %s #%d', $tenant, $i);
            $body     = $this->payload($tenants[$tenant]['phone'], '3460000' . $i, $text);
            $response = $this->handle($kernel, $this->signedPost($tenant, $body, $tenants[$tenant]['secret']));
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $at);
            self::assertSame([[$tenant, $text]], $seen->getArrayCopy(), $at);

            $recorded = $mocks[$tenant]->recorded();
            self::assertCount(1, $recorded, $at);
            self::assertSame('reply to ' . $text, $recorded[0]['body']['text']['body'] ?? null, $at);
            // The other tenant's mock was cleared at the start of its own request, never carries this one's data.
            foreach ($mocks[$other]->recorded() as $entry) {
                self::assertStringNotContainsString($text, json_encode($entry, JSON_THROW_ON_ERROR), $at);
            }

            // The other tenant's secret, or its payload on this tenant's URL, is rejected and dispatches nothing.
            $seen->exchangeArray([]);
            $response = $this->handle($kernel, $this->signedPost($tenant, $body, $tenants[$other]['secret']));
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $at);
            $foreign  = $this->payload($tenants[$other]['phone'], '3470000' . $i, 'foreign ' . $i);
            $response = $this->handle($kernel, $this->signedPost($tenant, $foreign, $tenants[$tenant]['secret']));
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $at);
            self::assertSame([], $seen->getArrayCopy(), $at);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MEMORY_MARGIN_BYTES, $growth, sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));

        $kernel->shutdown();
    }

    private function handle(KeptAliveKernel $kernel, Request $request): Response
    {
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        $kernel->terminate($request, $response);

        return $response;
    }

    private function signedPost(string $tenant, string $body, string $secret): Request
    {
        return Request::create(
            '/whatsapp/webhook/' . $tenant,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, $secret)],
            $body,
        );
    }

    private function payload(string $phoneNumberId, string $from, string $text): string
    {
        return json_encode([
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => 'waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'messages' => [[
                            'id'        => 'wamid.' . $from,
                            'from'      => $from,
                            'type'      => 'text',
                            'timestamp' => '1700000000',
                            'text'      => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
