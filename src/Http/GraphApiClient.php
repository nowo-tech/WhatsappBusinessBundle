<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function ceil;
use function defined;
use function extension_loaded;
use function is_array;
use function ltrim;
use function parse_url;
use function rtrim;
use function sprintf;

use const CURLOPT_CONNECTTIMEOUT;
use const PHP_URL_HOST;

/**
 * Low-level Graph HTTP client. Does not log tokens or request bodies.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class GraphApiClient
{
    /**
     * Creates the Graph client for one profile; every request uses the profile timeouts.
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private CloudApiProfile $profile,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Posts a JSON body to a Graph path and returns the decoded response.
     *
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $json): array
    {
        return $this->request('POST', $path, [
            'json' => $json,
        ]);
    }

    /**
     * Sends a GET to a Graph path with query parameters and returns the decoded response.
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [
            'query' => $query,
        ]);
    }

    /**
     * Sends a DELETE to a Graph path and returns the decoded response.
     *
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Uploads a local file as multipart form data to a Graph path and returns the decoded response.
     *
     * @return array<string, mixed>
     */
    public function postMedia(string $path, string $filePath, string $mimeType): array
    {
        $form = new FormDataPart([
            'messaging_product' => 'whatsapp',
            'type'              => $mimeType,
            'file'              => DataPart::fromPath($filePath, null, $mimeType),
        ]);

        return $this->request('POST', $path, [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body'    => $form->bodyToIterable(),
        ]);
    }

    /**
     * Downloads a media URL (allow-listed Meta hosts only, no redirects, profile timeout) and returns the bytes.
     */
    public function downloadRaw(string $url): string
    {
        if (!MediaDownloadUrlPolicy::isAllowed($url, $this->profile->graphBaseUri)) {
            $this->logger->warning('WhatsApp media download URL rejected.', [
                'profile' => $this->profile->name,
                'host'    => parse_url($url, PHP_URL_HOST),
            ]);
            throw GraphApiException::mediaUrlRejected();
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout'       => $this->profile->timeout,
                'max_duration'  => $this->profile->timeout,
                'max_redirects' => 0,
                'headers'       => [
                    'Authorization' => 'Bearer ' . $this->profile->accessToken,
                ],
            ]);

            return $response->getContent();
        } catch (HttpExceptionInterface $exception) {
            $status = $exception->getResponse()->getStatusCode();
            $this->logger->error('WhatsApp media download failed.', [
                'profile' => $this->profile->name,
                'status'  => $status,
            ]);
            throw GraphApiException::mediaDownloadFailed($status);
        } catch (TransportExceptionInterface) {
            $this->logger->error('WhatsApp media download failed.', [
                'profile' => $this->profile->name,
            ]);
            throw GraphApiException::transportFailure();
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $url = sprintf(
            '%s/%s/%s',
            rtrim($this->profile->graphBaseUri, '/'),
            $this->profile->graphVersion,
            ltrim($path, '/'),
        );

        // The token is only ever written to the local $sent copy: PHP exception traces keep the current
        // value of parameters (zend.exception_ignore_args=Off), so it must never land in $options.
        $sent                  = $options;
        $sent['timeout']       = $this->profile->timeout;
        $sent['max_duration']  = $this->profile->timeout;
        $sent['max_redirects'] = 0;
        if (extension_loaded('curl') && defined('CURLOPT_CONNECTTIMEOUT')) {
            $sent['extra']['curl'][CURLOPT_CONNECTTIMEOUT] = (int) ceil($this->profile->connectTimeout);
        }
        $headers = $sent['headers'] ?? [];
        if (!is_array($headers)) {
            $headers = [];
        }
        $headers['Authorization'] = 'Bearer ' . $this->profile->accessToken;
        $headers['Accept']        = $headers['Accept'] ?? 'application/json';
        $sent['headers']          = $headers;

        $status = 0;
        try {
            $response = $this->httpClient->request($method, $url, $sent);
            $status   = $response->getStatusCode();
            $payload  = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            $this->logger->warning('WhatsApp Cloud API returned a non-JSON response.', [
                'profile' => $this->profile->name,
                'status'  => $status,
                'path'    => $path,
            ]);
            throw GraphApiException::invalidResponse($status);
        } catch (TransportExceptionInterface) {
            $this->logger->error('WhatsApp Cloud API transport failure.', [
                'profile' => $this->profile->name,
                'method'  => $method,
                'path'    => $path,
            ]);
            throw GraphApiException::transportFailure();
        }

        if ($status >= 400 || isset($payload['error'])) {
            $this->logger->warning('WhatsApp Cloud API error.', [
                'profile' => $this->profile->name,
                'status'  => $status,
                'path'    => $path,
            ]);
            throw GraphApiException::fromGraph($payload, $status);
        }

        return $payload;
    }
}
