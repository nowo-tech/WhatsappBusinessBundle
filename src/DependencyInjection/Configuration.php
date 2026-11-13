<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_string;
use function preg_match;
use function str_starts_with;

/**
 * Configuration tree for nowo_whatsapp_business (named profiles).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * Builds the nowo_whatsapp_business configuration tree (default profile + named profiles).
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nowo_whatsapp_business');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_profile')
                    ->info('Name of the profile used when no profile is requested')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('profiles')
                    ->info('Named Cloud API profiles (phone number id, token, timeouts, webhook secrets)')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('transport')
                                ->info('cloud = Graph API; mock = in-memory (demos/tests)')
                                ->values(['cloud', 'mock'])
                                ->defaultValue('cloud')
                            ->end()
                            ->scalarNode('transport_service')
                                ->info('Id of a host service implementing Transport\\TransportInterface that replaces the built-in transport of this profile (SPI). Null keeps `transport`.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('phone_number_id')
                                ->info('WhatsApp phone number id used as sender')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('access_token')
                                ->info('Permanent or system-user Cloud API access token (never log this value)')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('whatsapp_business_account_id')
                                ->info('WABA id; required to list/manage message templates')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('graph_version')
                                ->info('Graph API version, e.g. v21.0')
                                ->defaultValue('v21.0')
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn ($v): bool => !is_string($v) || preg_match('/^v\d+\.\d+$/', $v) !== 1)
                                    ->thenInvalid('graph_version must match vMAJOR.MINOR (e.g. v21.0).')
                                ->end()
                            ->end()
                            ->scalarNode('graph_base_uri')
                                ->info('Graph API base URI (https only)')
                                ->defaultValue('https://graph.facebook.com')
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static function ($v): bool {
                                        return !is_string($v)
                                            || !str_starts_with($v, 'https://')
                                            || preg_match('#\s#', $v) === 1;
                                    })
                                    ->thenInvalid('graph_base_uri must be an https:// URL without whitespace.')
                                ->end()
                            ->end()
                            ->floatNode('timeout')
                                ->info('HTTP request timeout in seconds (HttpClient max_duration); REQ-RUNTIME-001')
                                ->defaultValue(10.0)
                                ->min(0.1)
                            ->end()
                            ->floatNode('connect_timeout')
                                ->info('HTTP connect timeout in seconds when curl is available; REQ-RUNTIME-001')
                                ->defaultValue(5.0)
                                ->min(0.1)
                            ->end()
                            ->scalarNode('app_secret')
                                ->info('Meta app secret used to verify X-Hub-Signature-256')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('webhook_verify_token')
                                ->info('Token expected on GET hub.verify_token')
                                ->defaultNull()
                            ->end()
                            ->booleanNode('allow_unsigned_webhooks')
                                ->info('Allow POST webhooks without HMAC when app_secret is empty (demo only)')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'default' => [
                            'transport'                    => 'mock',
                            'phone_number_id'              => 'demo-phone',
                            'access_token'                 => 'demo-token',
                            'whatsapp_business_account_id' => 'demo-waba',
                            'graph_version'                => 'v21.0',
                            'graph_base_uri'               => 'https://graph.facebook.com',
                            'timeout'                      => 10.0,
                            'connect_timeout'              => 5.0,
                            'app_secret'                   => null,
                            'webhook_verify_token'         => null,
                            'allow_unsigned_webhooks'      => false,
                        ],
                    ])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
