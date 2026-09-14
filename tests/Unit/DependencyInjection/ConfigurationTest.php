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
}
