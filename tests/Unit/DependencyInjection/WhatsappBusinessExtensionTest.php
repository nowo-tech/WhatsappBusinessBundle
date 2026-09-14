<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\DependencyInjection;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
}
