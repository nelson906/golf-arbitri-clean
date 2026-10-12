<?php

namespace App\Support;

/**
 * Link firmato verso l'app Schema Partenze (simulatore tempi di partenza).
 *
 * Formato identico a App\Support\SsoToken di laravel-schema-partenze:
 * base64url(json) . '.' . base64url(hmac_sha256(base64url(json), segreto))
 * Payload: {"aud":"partenze","email":...,"name":...,"iat":<unix>,"nonce":...}
 * Se cambi il formato qui, cambialo anche la' (SsoTest lo verifica).
 */
final class PartenzeSsoToken
{
    public static function make(string $email, string $name, int $issuedAt, string $nonce, string $secret): string
    {
        $json = json_encode([
            'aud'   => 'partenze',
            'email' => $email,
            'name'  => $name,
            'iat'   => $issuedAt,
            'nonce' => $nonce,
        ], JSON_THROW_ON_ERROR);

        $body = self::b64($json);

        return $body.'.'.self::b64(hash_hmac('sha256', $body, $secret, true));
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
