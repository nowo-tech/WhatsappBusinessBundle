<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\DependencyInjection;

use Nowo\WhatsappBusinessBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertSame('default', $config['default_profile']);
        self::assertSame('mock', $config['profiles']['default']['transport']);
        self::assertFalse($config['profiles']['default']['allow_unsigned_webhooks']);
    }

    public function testRejectsHttpGraphBase(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                    'graph_base_uri'  => 'http://example.com',
                ],
            ],
        ]]);
    }

    public function testRejectsGraphVersion(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                    'graph_version'   => '21.0',
                ],
            ],
        ]]);
    }

    public function testWhitespaceInUri(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                    'graph_base_uri'  => 'https://graph.facebook.com/ path',
                ],
            ],
        ]]);
    }

    public function testDefaultProfileHasNoTransportService(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertNull($config['profiles']['default']['transport_service']);
    }

    public function testTransportServiceAloneImpliesCustom(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'phone_number_id'   => '1',
                    'access_token'      => 't',
                    'transport_service' => 'app.my_transport',
                ],
            ],
        ]]);

        self::assertSame('custom', $config['profiles']['default']['transport']);
        self::assertSame('app.my_transport', $config['profiles']['default']['transport_service']);
    }

    public function testExplicitCustomWithServiceIsAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'transport'         => 'custom',
                    'phone_number_id'   => '1',
                    'access_token'      => 't',
                    'transport_service' => 'app.my_transport',
                ],
            ],
        ]]);

        self::assertSame('custom', $config['profiles']['default']['transport']);
    }

    public function testEmptyTransportServiceDoesNotImplyCustom(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'phone_number_id'   => '1',
                    'access_token'      => 't',
                    'transport_service' => '',
                ],
            ],
        ]]);

        self::assertSame('cloud', $config['profiles']['default']['transport']);
    }

    public function testCustomTransportWithoutServiceIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('transport: custom requires transport_service');
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'transport'       => 'custom',
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                ],
            ],
        ]]);
    }

    public function testTransportServiceWithAnotherTransportIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('transport_service is only used with transport: custom');
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'transport'         => 'mock',
                    'phone_number_id'   => '1',
                    'access_token'      => 't',
                    'transport_service' => 'app.my_transport',
                ],
            ],
        ]]);
    }

    public function testUnknownTransportIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'transport'       => 'carrier-pigeon',
                    'phone_number_id' => '1',
                    'access_token'    => 't',
                ],
            ],
        ]]);
    }
}
