<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Integration\Worker;

use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

use function sys_get_temp_dir;

/**
 * Host-like kernel for the kept-alive worker test: two mock profiles (two tenants) with their own secrets.
 */
final class KeptAliveKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new WhatsappBusinessBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret'                => 'test',
                'test'                  => true,
                'http_method_override'  => false,
                'handle_all_throwables' => true,
                'php_errors'            => ['log' => false],
                'http_client'           => ['enabled' => true],
                'router'                => [
                    'resource' => __DIR__ . '/../Fixtures/routes.yaml',
                    'utf8'     => true,
                ],
            ]);
            $container->loadFromExtension('nowo_whatsapp_business', [
                'default_profile' => 'alpha',
                'profiles'        => [
                    'alpha' => [
                        'transport'            => 'mock',
                        'phone_number_id'      => '1000001',
                        'access_token'         => 'alpha-token',
                        'app_secret'           => 'alpha-secret',
                        'webhook_verify_token' => 'alpha-verify',
                    ],
                    'beta' => [
                        'transport'            => 'mock',
                        'phone_number_id'      => '2000002',
                        'access_token'         => 'beta-token',
                        'app_secret'           => 'beta-secret',
                        'webhook_verify_token' => 'beta-verify',
                    ],
                ],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/whatsapp_business_kept_alive/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/whatsapp_business_kept_alive/log';
    }
}
