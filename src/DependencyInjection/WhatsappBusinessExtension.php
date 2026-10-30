<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Config\TransportKind;
use Nowo\WhatsappBusinessBundle\EventSubscriber\ResetMockTransportsOnRequestSubscriber;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

use function array_keys;
use function class_exists;
use function interface_exists;
use function sprintf;

/**
 * Loads services and wires one WhatsappClient per named profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WhatsappBusinessExtension extends Extension
{
    /**
     * Loads the bundle services and registers one profile, transport and client per configured profile.
     *
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        if (class_exists(Command::class)) {
            $loader->load('console.yaml');
        }
        if (interface_exists(MessageBusInterface::class)) {
            $loader->load('messenger.yaml');
        }

        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $defaultProfile = (string) $config['default_profile'];
        if (!isset($config['profiles'][$defaultProfile])) {
            throw UnknownProfileException::forProfile($defaultProfile);
        }

        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : null;

        $clientRefs = [];
        foreach ($config['profiles'] as $name => $profileConfig) {
            // Fail closed: unsigned webhooks on a public prod endpoint would accept forged notifications.
            if ($environment === 'prod' && $profileConfig['allow_unsigned_webhooks'] === true && ($profileConfig['app_secret'] ?? '') === '') {
                throw new InvalidConfigurationException(sprintf('nowo_whatsapp_business.profiles.%s.allow_unsigned_webhooks cannot be true without app_secret in the "prod" environment: set app_secret so webhooks are HMAC-verified.', $name));
            }

            $clientRefs[(string) $name] = new Reference($this->registerProfile($container, (string) $name, $profileConfig));
        }

        $mockTransports = [];
        foreach ($config['profiles'] as $name => $profileConfig) {
            if (TransportKind::from((string) $profileConfig['transport']) === TransportKind::Mock) {
                // Only already-instantiated transports are reset (same strategy as services_resetter).
                $mockTransports[] = new Reference(
                    sprintf('nowo_whatsapp_business.transport.%s', $name),
                    ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE,
                );
            }
        }
        if ($mockTransports !== []) {
            $container->register(ResetMockTransportsOnRequestSubscriber::class, ResetMockTransportsOnRequestSubscriber::class)
                ->setArgument('$transports', new IteratorArgument($mockTransports))
                ->addTag('kernel.event_subscriber');
        }

        $registry = new Definition(WhatsappClientRegistry::class, [
            '$clients'        => $clientRefs,
            '$defaultProfile' => $defaultProfile,
        ]);
        $registry->setPublic(false);
        $container->setDefinition('nowo_whatsapp_business.registry', $registry);
        $container->setAlias(WhatsappClientRegistryInterface::class, 'nowo_whatsapp_business.registry')
            ->setPublic(false);

        $defaultServiceId = sprintf('nowo_whatsapp_business.client.%s', $defaultProfile);
        $container->setAlias(WhatsappClientInterface::class, $defaultServiceId)->setPublic(false);
        $container->setAlias(WhatsappClient::class, $defaultServiceId)->setPublic(false);
        $container->setAlias('nowo_whatsapp_business.client', $defaultServiceId)->setPublic(true);

        $container->setParameter('nowo_whatsapp_business.default_profile', $defaultProfile);
        $container->setParameter('nowo_whatsapp_business.profiles', array_keys($config['profiles']));
    }

    /**
     * @param array<string, mixed> $profileConfig
     */
    private function registerProfile(ContainerBuilder $container, string $name, array $profileConfig): string
    {
        $waba = $profileConfig['whatsapp_business_account_id'];
        if ($waba === '') {
            $waba = null;
        }
        $appSecret = $profileConfig['app_secret'];
        if ($appSecret === '') {
            $appSecret = null;
        }
        $verifyToken = $profileConfig['webhook_verify_token'];
        if ($verifyToken === '') {
            $verifyToken = null;
        }

        $profileDef = new Definition(CloudApiProfile::class, [
            '$name'                      => $name,
            '$phoneNumberId'             => (string) $profileConfig['phone_number_id'],
            '$accessToken'               => (string) $profileConfig['access_token'],
            '$transport'                 => TransportKind::from((string) $profileConfig['transport']),
            '$whatsappBusinessAccountId' => $waba,
            '$graphVersion'              => (string) $profileConfig['graph_version'],
            '$graphBaseUri'              => (string) $profileConfig['graph_base_uri'],
            '$timeout'                   => (float) $profileConfig['timeout'],
            '$connectTimeout'            => (float) $profileConfig['connect_timeout'],
            '$appSecret'                 => $appSecret,
            '$webhookVerifyToken'        => $verifyToken,
            '$allowUnsignedWebhooks'     => (bool) $profileConfig['allow_unsigned_webhooks'],
        ]);
        $profileId = sprintf('nowo_whatsapp_business.profile.%s', $name);
        $profileDef->setPublic(false);
        $container->setDefinition($profileId, $profileDef);

        $transportKind = TransportKind::from((string) $profileConfig['transport']);
        if ($transportKind === TransportKind::Mock) {
            $transport = new Definition(MockTransport::class, [
                '$profile' => new Reference($profileId),
            ]);
            $transport->addTag('kernel.reset', ['method' => 'reset']);
        } else {
            $graph = new Definition(GraphApiClient::class, [
                '$httpClient' => new Reference('http_client'),
                '$profile'    => new Reference($profileId),
                '$logger'     => new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);
            $graphId = sprintf('nowo_whatsapp_business.graph.%s', $name);
            $graph->setPublic(false);
            $container->setDefinition($graphId, $graph);

            $transport = new Definition(CloudApiTransport::class, [
                '$graph'   => new Reference($graphId),
                '$profile' => new Reference($profileId),
            ]);
        }

        $transportId = sprintf('nowo_whatsapp_business.transport.%s', $name);
        $transport->setPublic(false);
        $container->setDefinition($transportId, $transport);

        $client = new Definition(WhatsappClient::class, [
            '$profile'         => new Reference($profileId),
            '$transport'       => new Reference($transportId),
            '$eventDispatcher' => new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            '$logger'          => new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
        ]);
        $clientId = sprintf('nowo_whatsapp_business.client.%s', $name);
        $client->setPublic(false);
        $container->setDefinition($clientId, $client);

        return $clientId;
    }

    /**
     * Returns the configuration alias (nowo_whatsapp_business).
     */
    public function getAlias(): string
    {
        return 'nowo_whatsapp_business';
    }
}
