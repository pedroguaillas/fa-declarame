<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SSOTokenService
{
    private const TOKEN_VERSION = 'v1'; 

    /**
     * Generar token SSO encriptado
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $email
     * @param string $name
     * @param int $expiresInMinutes
     * @return string Token generado y firmado
     */
    public function generate(
        int $userId,
        string $tenantId,
        string $email,
        string $name,
        string $username,
        bool $is_active = true,
        int $expiresInMinutes = 5,
    ): string {
        // 1. Crear payload con datos del usuario
        $payload = [
            'v' => self::TOKEN_VERSION,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'name' => $name,
            'username' => $username,
            'is_active' => $is_active,
            'iat' => Carbon::now()->timestamp,              // Issued At (cuando se creó)
            'exp' => Carbon::now()->addMinutes($expiresInMinutes)->timestamp, // Expiration
            'jti' => $this->generateJTI(),                  // JWT ID (único por token)
        ];

        // 2. Convertir a JSON
        $json = json_encode($payload);

        // 3. Encriptar usando la APP_KEY de Laravel (AES-256-CBC)
        $encrypted = Crypt::encryptString($json);

        // 4. Codificar en base64 URL-safe
        $token = $this->base64UrlEncode($encrypted);

        // 5. Agregar prefijo para identificación
        return 'sso.' . $token;
    }

    /**
     * Validar y decodificar token
     * 
     * @param string $token
     * @return array|null Datos del token o null si es inválido
     */
    public function validate(string $token): ?array
    {
        try {
            // 1. Verificar prefijo
            if (!str_starts_with($token, 'sso.')) {
                Log::warning('Token SSO sin prefijo válido');
                return null;
            }

            // 2. Remover prefijo
            $token = substr($token, 4);

            // 3. Decodificar base64 URL-safe
            $encrypted = $this->base64UrlDecode($token);

            // 4. Desencriptar
            $json = Crypt::decryptString($encrypted);

            // 5. Parsear JSON
            $payload = json_decode($json, true);

            if (!$payload) {
                Log::warning('Token SSO con JSON inválido');
                return null;
            }

            // 6. Verificar campos requeridos
            $requiredFields = ['user_id', 'tenant_id', 'email', 'name', 'iat', 'exp', 'jti'];
            foreach ($requiredFields as $field) {
                if (!isset($payload[$field])) {
                    Log::warning("Token SSO sin campo requerido: {$field}");
                    return null;
                }
            }

            // 7. Verificar expiración
            if ($payload['exp'] < Carbon::now()->timestamp) {
                Log::info('Token SSO expirado', [
                    'expired_at' => Carbon::createFromTimestamp($payload['exp'])->toDateTimeString(),
                    'current_time' => Carbon::now()->toDateTimeString(),
                ]);
                return null;
            }

            // 8. Verificar que el token no sea del futuro
            if ($payload['iat'] > Carbon::now()->addMinute()->timestamp) {
                Log::warning('Token SSO con fecha futura');
                return null;
            }

            // 9. Verificar que el token no haya sido usado (opcional)
            if ($this->isTokenUsed($payload['jti'])) {
                Log::warning('Token SSO ya fue utilizado', ['jti' => $payload['jti']]);
                return null;
            }

            // 10. Marcar token como usado
            $this->markTokenAsUsed($payload['jti'], $payload['exp']);

            return $payload;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Token SSO con encriptación inválida: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error('Error validando token SSO: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generar JWT ID único
     */
    private function generateJTI(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Verificar si el token ya fue usado (previene replay attacks)
     */
    private function isTokenUsed(string $jti): bool
    {
        return cache()->has("sso_token_used:{$jti}");
    }

    /**
     * Marcar token como usado
     */
    private function markTokenAsUsed(string $jti, int $expiration): void
    {
        $expiresAt = Carbon::createFromTimestamp($expiration);
        cache()->put("sso_token_used:{$jti}", true, $expiresAt);
    }

    /**
     * Base64 URL-safe encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Obtener tiempo restante del token en segundos
     */
    public function getTimeToExpire(string $token): ?int
    {
        $payload = $this->validate($token);

        if (!$payload) {
            return null;
        }

        $remaining = $payload['exp'] - Carbon::now()->timestamp;
        return max(0, $remaining);
    }
}
