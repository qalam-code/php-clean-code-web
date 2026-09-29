<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Infrastructure;

use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;
use RuntimeException;

/** Adaptateur de session native PHP avec configuration de cookie securisee. */
final class SessionPhp implements StockageSession
{
    public function __construct($cookieSecurise = null)
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('Les sessions PHP sont desactivees.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_strict_mode') !== '1') {
                throw new RuntimeException('La session active doit utiliser le mode strict.');
            }
            return;
        }
        if (ini_set('session.use_strict_mode', '1') === false
            || ini_set('session.use_only_cookies', '1') === false
            || ini_set('session.use_trans_sid', '0') === false
        ) {
            throw new RuntimeException('Impossible de configurer les options de session securisees.');
        }

        $parametres = session_get_cookie_params();
        $secure = $cookieSecurise === null ? $this->requeteSecurisee() : (bool) $cookieSecurise;
        if (PHP_VERSION_ID >= 70300) {
            $parametres['secure'] = $secure;
            $parametres['httponly'] = true;
            $parametres['samesite'] = 'Lax';
            $configure = session_set_cookie_params($parametres);
        } else {
            $configure = session_set_cookie_params(
                $parametres['lifetime'],
                $parametres['path'],
                $parametres['domain'],
                $secure,
                true
            );
        }
        if (!$configure || !session_start()) {
            throw new RuntimeException('Impossible de demarrer la session PHP.');
        }
    }

    public function lire(string $cle, $defaut = null)
    {
        return array_key_exists($cle, $_SESSION) ? $_SESSION[$cle] : $defaut;
    }

    public function ecrire(string $cle, $valeur)
    {
        $_SESSION[$cle] = $valeur;
    }

    private function requeteSecurisee(): bool
    {
        return isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}