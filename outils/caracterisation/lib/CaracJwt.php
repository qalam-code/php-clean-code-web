<?php
/**
 * Fabrique de jetons JWT pour les cas d'authentification.
 *
 * Reproduit volontairement a l'identique l'algorithme de
 * l'implementation JWT du projet, afin de pouvoir forger les jetons
 * degrades que l'API doit rejeter (expire, signature invalide) sans
 * dependre du comportement du serveur.
 *
 * Compatible PHP 7.0 strict.
 */
final class CaracJwt
{
    /**
     * @param array  $payload
     * @param string $secret
     * @param int    $validity duree de validite en secondes ; negative pour un jeton deja expire
     * @return string
     */
    public static function forge(array $payload, $secret, $validity = 3600)
    {
        $headers = array('alg' => 'HS256', 'typ' => 'JWT');

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + (int) $validity;

        $base64headers = self::base64UrlEncode(json_encode($headers));
        $base64payload = self::base64UrlEncode(json_encode($payload));

        $signature       = hash_hmac('sha256', $base64headers . '.' . $base64payload, $secret, true);
        $base64signature = self::base64UrlEncode($signature);

        return $base64headers . '.' . $base64payload . '.' . $base64signature;
    }

    /**
     * Jeton bien forme au sens de la validation de format, mais dont la
     * signature ne correspond pas au secret du serveur.
     *
     * @param array $payload
     * @return string
     */
    public static function forgeWithWrongSignature(array $payload)
    {
        return self::forge($payload, 'mauvais-secret-de-test', 3600);
    }

    /**
     * @param string $raw
     * @return string
     */
    private static function base64UrlEncode($raw)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($raw));
    }
}

