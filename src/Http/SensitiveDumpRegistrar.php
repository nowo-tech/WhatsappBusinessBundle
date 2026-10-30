<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Nowo\WhatsappBusinessBundle\Webhook\IncomingMessage;
use Nowo\WhatsappBusinessBundle\Webhook\StatusUpdate;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookNotification;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

use function method_exists;

/**
 * Registers VarDumper casters so dump()/profiler never print Cloud API secrets.
 *
 * Casters are process-global (no request data). Re-registering is idempotent.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SensitiveDumpRegistrar
{
    /**
     * Registers the VarDumper casters for every class that carries secrets (idempotent).
     */
    public static function register(): void
    {
        // var-dumper is optional (pulled by Symfony error-handler in full apps).
        if (!class_exists(AbstractCloner::class)) {
            return; // @codeCoverageIgnore
        }

        $classes = [
            CloudApiProfile::class,
            GraphApiException::class,
            IncomingMessage::class,
            StatusUpdate::class,
            WebhookNotification::class,
            MediaDownload::class,
            MediaId::class,
            MessageResult::class,
        ];
        foreach ($classes as $class) {
            // Process-global VarDumper map; values are static callables, not request state.
            // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
            AbstractCloner::$defaultCasters[$class] = self::castUsingDebugInfo(...);
        }
    }

    /**
     * VarDumper caster that dumps an object through its redacting __debugInfo().
     *
     * @param array<string, mixed> $a
     *
     * @return array<string, mixed>
     */
    public static function castUsingDebugInfo(object $object, array $a): array
    {
        if (!method_exists($object, '__debugInfo')) {
            return $a;
        }

        /** @var array<string, mixed> $info */
        $info = $object->__debugInfo();

        return $info;
    }
}
