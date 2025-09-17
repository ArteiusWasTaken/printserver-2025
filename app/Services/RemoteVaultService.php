<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class RemoteVaultService
{
    private string $baseUrl;
    private Client $http;

    public function __construct(?string $baseUrl = null, ?Client $client = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? env('VAULT_BASE_URL', 'http://rest.crmomg.mx'), '/');
        $this->http    = $client ?? new Client(['timeout' => 8.0]);
    }

    /**
     * @throws GuzzleException
     */
    public function getValid(string $clientId): ?string
    {
        $res  = $this->http->get($this->baseUrl . '/vault/' . urlencode($clientId));
        $body = trim((string) $res->getBody());

        if ($body === '' || strtolower($body) === 'null') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) return $decoded;
            if (is_array($decoded) && isset($decoded['token']) && is_string($decoded['token'])) {
                return $decoded['token'];
            }
        }

        return $body;
    }

    /**
     * @throws GuzzleException
     */
    public function put(string $clientId, string $plainToken): ?string
    {
        $payload = json_encode(['clientId' => $clientId, 'plainToken' => $plainToken], JSON_UNESCAPED_SLASHES);
        $res  = $this->http->post($this->baseUrl . '/vault', [
            'form_params' => ['data' => $payload],
            'headers'     => ['Accept' => 'application/json,text/plain'],
        ]);
        $body = trim((string) $res->getBody());
        return $body !== '' && strtolower($body) !== 'null' ? $body : null;
    }
}
