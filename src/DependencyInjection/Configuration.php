<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection;

use Nowo\WhatsappBusinessBundle\Config\TransportKind;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_key_exists;
use function is_array;
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
                        // A profile that only names a transport_service is a custom profile, so the host does not
                        // have to repeat `transport: custom`. An explicit `transport` is never overwritten here;
                        // the validations below refuse the contradictory combinations.
                        ->beforeNormalization()
                            ->ifTrue(static function ($v): bool {
                                return is_array($v)
                                    && !array_key_exists('transport', $v)
                                    && is_string($v['transport_service'] ?? null)
                                    && $v['transport_service'] !== '';
                            })
                            ->then(static function ($v): array {
                                /** @var array<string, mixed> $profile */
                                $profile              = $v;
                                $profile['transport'] = TransportKind::Custom->value;

                                return $profile;
                            })
                        ->end()
                        ->validate()
                            ->ifTrue(static function ($v): bool {
                                return is_array($v)
                                    && ($v['transport'] ?? null) === TransportKind::Custom->value
                                    && (string) ($v['transport_service'] ?? '') === '';
                            })
                            ->thenInvalid('transport: custom requires transport_service (the service id of your Nowo\WhatsappBusinessBundle\Transport\TransportInterface implementation).')
                        ->end()
                        ->validate()
                            ->ifTrue(static function ($v): bool {
                                return is_array($v)
                                    && ($v['transport'] ?? null) !== TransportKind::Custom->value
                                    && (string) ($v['transport_service'] ?? '') !== '';
                            })
                            ->thenInvalid('transport_service is only used with transport: custom — set transport: custom, or omit transport so it is inferred.')
                        ->end()
                        ->children()
                            ->enumNode('transport')
                                ->info('cloud = Graph API; mock = in-memory (demos/tests); custom = the service named in transport_service')
                                ->values(['cloud', 'mock', 'custom'])
                                ->defaultValue('cloud')
                            ->end()
                            ->scalarNode('transport_service')
                                ->info('Service id of a host TransportInterface implementation; implies transport: custom (REQ-ARCH-001 rule 5)')
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
                            'transport_service'            => null,
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
