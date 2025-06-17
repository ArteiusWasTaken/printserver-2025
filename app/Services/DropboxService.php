<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DropboxService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected string $token;
    protected Client $client;

    public function __construct()
    {
        $this->clientId = config('services.dropbox.client_id');
        $this->clientSecret = config('services.dropbox.client_secret');
        $this->refreshToken = config('services.dropbox.refresh_token');
        $this->token = config('services.dropbox.token');
        $this->client = new Client();
    }

    /**
     * Renueva el access token de Dropbox y lo guarda en DROPBOX_TOKEN del .env
     */
    public function refreshAccessToken(): string
    {
        $url = 'https://api.dropbox.com/oauth2/token';
        $body = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ]);

        $authorization = base64_encode($this->clientId . ':' . $this->clientSecret);

        $response = $this->client->post($url, [
            'headers' => [
                'Authorization' => "Basic $authorization",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['access_token'])) {
            self::setEnvValue($data['access_token']);
            return $data['access_token'];
        }

        throw new \Exception('No se pudo renovar el access token de Dropbox');
    }

    /**
     * Actualiza o agrega una variable en el .env
     */
    private static function setEnvValue(string $value): void
    {
        $envPath = base_path('.env');
        $env = File::get($envPath);

        if (preg_match("/^DROPBOX_TOKEN=.*$/m", $env)) {
            $env = preg_replace("/^DROPBOX_TOKEN=.*$/m", "DROPBOX_TOKEN={$value}", $env);
        } else {
            $env .= "\nDROPBOX_TOKEN={$value}";
        }

        File::put($envPath, $env);
    }

    /**
     * Descarga un archivo en binario desde Dropbox.
     * Devuelve el contenido binario.
     */
    public function downloadFile(string $path): array
    {
        $url = 'https://content.dropboxapi.com/2/files/download';

        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Dropbox-API-Arg' => json_encode(['path' => $path])
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($status === 200) {
                return [
                    'success' => true,
                    'content' => $body,
                ];
            } else {
                // A veces Dropbox pone mensaje de error en headers o en body
                $mensaje = 'Error al descargar archivo';
                if (!empty($body)) {
                    // Algunos errores de Dropbox regresan JSON, otros texto plano
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error_summary'])) {
                        $mensaje = $decoded['error_summary'];
                    } else {
                        $mensaje = $body;
                    }
                }
                return [
                    'success' => false,
                    'status' => $status,
                    'message' => $mensaje,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Dropbox downloadFile error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function logVariableLocation(): string
    {
        $sis = 'BE'; // Front o Back
        $ini = 'WS'; // Primera letra del Controlador y Letra de la segunda Palabra
        $fin = 'APP'; // Últimas 3 letras del primer nombre del archivo
        $trace = debug_backtrace()[0];
        return ('<br> Código de Error: ' . $sis . $ini . $trace['line'] . $fin);
    }
}
