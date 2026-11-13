<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WhatsappBusinessBundle\DependencyInjection\Compiler\TransportServicePass;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Tests\Support\HostTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(TransportServicePass::class)]
final class TransportServicePassTest extends TestCase
{
    public function testDoesNothingWithoutTheParameter(): void
    {
        $container = new ContainerBuilder();
        (new TransportServicePass())->process($container);

        self::assertFalse($container->hasParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }

    public function testAcceptsAHostTransportAndRemovesTheParameter(): void
    {
        $container = $this->containerWith(['default' => 'app.host_transport']);
        $container->register('app.host_transport', HostTransport::class);

        (new TransportServicePass())->process($container);

        self::assertFalse($container->hasParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }

    public function testFollowsAnAliasChain(): void
    {
        $container = $this->containerWith(['default' => TransportInterface::class]);
        $container->register('app.host_transport', HostTransport::class);
        $container->setAlias('app.transport_alias', 'app.host_transport');
        $container->setAlias(TransportInterface::class, 'app.transport_alias');

        (new TransportServicePass())->process($container);

        self::assertTrue($container->hasAlias(TransportInterface::class));
    }

    public function testRefusesAnUnknownService(): void
    {
        $container = $this->containerWith(['default' => 'app.missing']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('profiles.default.transport_service names the service "app.missing", which is not defined.');
        (new TransportServicePass())->process($container);
    }

    public function testRefusesAServiceThatDoesNotImplementTheTransport(): void
    {
        $container = $this->containerWith(['default' => 'app.not_a_transport']);
        $container->register('app.not_a_transport', self::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement "' . TransportInterface::class . '"');
        (new TransportServicePass())->process($container);
    }

    public function testLeavesAFactoryDefinitionToTheContainer(): void
    {
        $container  = $this->containerWith(['default' => 'app.factory_transport']);
        $definition = new Definition(HostTransport::class);
        $definition->setFactory([HostTransport::class, 'create']);
        $container->setDefinition('app.factory_transport', $definition);

        (new TransportServicePass())->process($container);

        self::assertTrue($container->hasDefinition('app.factory_transport'));
    }

    public function testLeavesASyntheticDefinitionToTheContainer(): void
    {
        $container = $this->containerWith(['default' => 'app.synthetic_transport']);
        $container->register('app.synthetic_transport')->setSynthetic(true);

        (new TransportServicePass())->process($container);

        self::assertTrue($container->hasDefinition('app.synthetic_transport'));
    }

    public function testIgnoresADefinitionWhoseClassCannotBeLoaded(): void
    {
        $container = $this->containerWith(['default' => 'app.unloadable']);
        $container->register('app.unloadable', 'App\\Does\\Not\\Exist');

        (new TransportServicePass())->process($container);

        self::assertTrue($container->hasDefinition('app.unloadable'));
    }

    public function testIgnoresAnAliasToAnUndefinedService(): void
    {
        $container = $this->containerWith(['default' => 'app.transport_alias']);
        $container->setAlias('app.transport_alias', 'app.not_defined_yet');

        (new TransportServicePass())->process($container);

        self::assertTrue($container->hasAlias('app.transport_alias'));
    }

    public function testIgnoresANonArrayParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER, 'not-a-map');

        (new TransportServicePass())->process($container);

        self::assertFalse($container->hasParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }

    public function testSkipsEntriesThatAreNotProfileToServiceStrings(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER, [0 => 'app.missing', 'default' => 42]);

        (new TransportServicePass())->process($container);

        self::assertFalse($container->hasParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER));
    }

    /**
     * @param array<string, string> $services
     */
    private function containerWith(array $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER, $services);

        return $container;
    }
}
