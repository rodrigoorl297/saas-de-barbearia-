<?php
declare(strict_types=1);

/**
 * Cliente HTTP mínimo para a Evolution API (WhatsApp via Baileys).
 * Cada barbearia conecta o próprio número — não depende de aprovação de
 * template como a Cloud API da Meta, então dá pra mandar texto livre na hora.
 *
 * Instância/URL/API key vêm de Configurações (dono) — ver includes/whatsapp.php.
 */
final class EvolutionClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function request(string $method, string $path, ?array $body = null): array
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Evolution API não configurada.');
        }
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Evolution: ' . ($err ?: 'falha na requisição'));
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw];
        }
        if ($code >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $raw;
            if (is_array($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            }
            throw new RuntimeException('Evolution HTTP ' . $code . ': ' . (string)$msg);
        }
        return $decoded;
    }

    /** Cria a instância na Evolution (uma vez só, por barbearia). */
    public function createInstance(string $instanceName): array
    {
        return $this->request('POST', '/instance/create', [
            'instanceName' => $instanceName,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]);
    }

    /** Pede o QR Code pra parear o WhatsApp da barbearia. */
    public function connect(string $instanceName): array
    {
        return $this->request('GET', '/instance/connect/' . rawurlencode($instanceName));
    }

    public function connectionState(string $instanceName): array
    {
        return $this->request('GET', '/instance/connectionState/' . rawurlencode($instanceName));
    }

    public function logout(string $instanceName): array
    {
        return $this->request('DELETE', '/instance/logout/' . rawurlencode($instanceName));
    }

    /** Envia texto livre — sem precisar de template aprovado. */
    public function sendText(string $instanceName, string $number, string $text): array
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if ($digits === '') {
            throw new InvalidArgumentException('Número inválido.');
        }
        if (!str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        return $this->request('POST', '/message/sendText/' . rawurlencode($instanceName), [
            'number' => $digits,
            'text' => $text,
        ]);
    }

    /** Extrai o estado da conexão: open (conectado) | connecting | close. */
    public static function parseConnectionState(array $resp): string
    {
        $state = $resp['instance']['state']
            ?? $resp['state']
            ?? $resp['status']
            ?? $resp['connectionStatus']
            ?? '';
        $state = strtolower((string)$state);
        if (in_array($state, ['open', 'connected', 'online'], true)) {
            return 'open';
        }
        if (in_array($state, ['connecting', 'qrcode', 'pairing'], true)) {
            return 'connecting';
        }
        return 'close';
    }

    /** Acha o base64 do QR Code na resposta de connect()/createInstance(). */
    public static function extractQr(array $resp): ?string
    {
        $candidates = [
            $resp['base64'] ?? null,
            $resp['qrcode']['base64'] ?? null,
            is_array($resp['qrcode'] ?? null) ? null : ($resp['qrcode'] ?? null),
            $resp['response']['qrcode']['base64'] ?? null,
            $resp['response']['base64'] ?? null,
        ];
        foreach ($candidates as $c) {
            if (!is_string($c) || $c === '') {
                continue;
            }
            if (str_starts_with($c, 'data:image')) {
                return $c;
            }
            if (preg_match('/^[A-Za-z0-9+\/=]{80,}$/', $c)) {
                return 'data:image/png;base64,' . $c;
            }
        }
        return null;
    }
}
