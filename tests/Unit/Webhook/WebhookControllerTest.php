<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Webhook;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Event\IncomingMessageEvent;
use Nowo\WhatsappBusinessBundle\Event\StatusUpdateEvent;
use Nowo\WhatsappBusinessBundle\Event\WebhookReceivedEvent;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookController;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookParser;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(WebhookController::class)]
#[CoversClass(IncomingMessageEvent::class)]
#[CoversClass(StatusUpdateEvent::class)]
#[CoversClass(WebhookReceivedEvent::class)]
final class WebhookControllerTest extends TestCase
{
    private function controller(string $verify = 'verify-me', ?string $secret = 'app-secret', bool $allowUnsigned = false): WebhookController
    {
        $profile  = ProfileFactory::mock(allowUnsigned: $allowUnsigned, appSecret: $secret, verifyToken: $verify);
        $client   = new WhatsappClient($profile, new MockTransport($profile));
        $registry = new WhatsappClientRegistry(['default' => $client], 'default');

        return new WebhookController($registry, new WebhookVerifier(), new WebhookParser(), new EventDispatcher());
    }

    public function testGetChallenge(): void
    {
        $request = Request::create('/whatsapp/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'verify-me',
            'hub.challenge'    => '12345',
        ]);
        $response = ($this->controller())($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('12345', $response->getContent());
    }

    public function testGetRejectsBadToken(): void
    {
        $request = Request::create('/whatsapp/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'wrong',
            'hub.challenge'    => '1',
        ]);
        self::assertSame(403, ($this->controller())($request)->getStatusCode());
    }

    public function testUnknownProfile(): void
    {
        $response = ($this->controller())(Request::create('/whatsapp/webhook/nope', 'GET'), 'nope');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testPostSigned(): void
    {
        $body    = '{"object":"whatsapp_business_account","entry":[{"changes":[{"value":{"messages":[{"id":"1","from":"34611","type":"text","timestamp":"1","text":{"body":"hi"}}],"statuses":[{"id":"2","status":"read","timestamp":"1"}]}}]}]}';
        $request = Request::create('/whatsapp/webhook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app-secret'),
        ], $body);
        $dispatcher = new EventDispatcher();
        $seen       = 0;
        $dispatcher->addListener(IncomingMessageEvent::class, static function () use (&$seen): void {
            ++$seen;
        });
        $profile    = ProfileFactory::mock();
        $client     = new WhatsappClient($profile, new MockTransport($profile));
        $registry   = new WhatsappClientRegistry(['default' => $client], 'default');
        $controller = new WebhookController($registry, new WebhookVerifier(), new WebhookParser(), $dispatcher);
        self::assertSame(200, $controller($request)->getStatusCode());
        self::assertSame(1, $seen);
    }

    public function testPostInvalidJsonStillOkWhenSigned(): void
    {
        $body    = 'not-json';
        $request = Request::create('/whatsapp/webhook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app-secret'),
        ], $body);
        self::assertSame(200, ($this->controller())($request)->getStatusCode());
    }

    public function testPostRejectsBadSignature(): void
    {
        $request = Request::create('/whatsapp/webhook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=dead',
        ], '{}');
        self::assertSame(403, ($this->controller())($request)->getStatusCode());
    }

    public function testEmptyProfileUsesDefault(): void
    {
        $request = Request::create('/whatsapp/webhook', 'GET', [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'verify-me',
            'hub.challenge'    => 'z',
        ]);
        self::assertSame('z', ($this->controller())($request, '')->getContent());
    }
}
