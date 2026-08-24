<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DemoControllerTest extends WebTestCase
{
    public function testHomePageReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'WhatsappBusinessBundle');
        self::assertSelectorExists('form');
    }

    public function testSendText(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/');
        $form    = $crawler->filter('form')->form([
            'send_demo[to]'   => '34611111111',
            'send_demo[body]' => 'hello demo',
        ]);
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Message id:');
    }
}
