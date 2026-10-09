<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Infrastructure;

use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;
use RuntimeException;

/**
 * Adaptateur de session native PHP pour le port StockageSession.
 * Il configure les options avant le demarrage de la session, puis expose ses
 * valeurs sans faire connaitre les superglobales au reste du framework.
 * Le navigateur conserve le cookie d'identifiant ; les donnees de $_SESSION
 * restent gerees cote serveur par PHP.
 */
final class SessionPhp implements StockageSession
{
    /**
     * @param bool|null $cookieSecurise true force Secure, false le desactive
     *        (utile en HTTP local), null le deduit de la requete HTTPS.
     */
    public function __construct($cookieSecurise = null)
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('Les sessions PHP sont desactivees.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Les options d'un cookie ne se reconfigurent pas apres session_start().
            // Une session deja active doit au moins respecter le mode strict ;
            // cet adaptateur ne peut plus corriger ses options de cookie.
            if (ini_get('session.use_strict_mode') !== '1') {
                throw new RuntimeException('La session active doit utiliser le mode strict.');
            }
            return;
        }
        // Refuser les identifiants fournis par URL et accepter uniquement le
        // cookie de session ; le mode strict limite les identifiants imposes.
        if (ini_set('session.use_strict_mode', '1') === false
            || ini_set('session.use_only_cookies', '1') === false
            || ini_set('session.use_trans_sid', '0') === false
        ) {
            throw new RuntimeException('Impossible de configurer les options de session securisees.');
        }

        $parametres = session_get_cookie_params();
        $secure = $cookieSecurise === null ? $this->requeteSecurisee() : (bool) $cookieSecurise;
        if (PHP_VERSION_ID >= 70300) {
            // PHP 7.3 ajoute l'option SameSite via le tableau de parametres.
            $parametres['secure'] = $secure;
            $parametres['httponly'] = true;
            $parametres['samesite'] = 'Lax';
            $configure = session_set_cookie_params($parametres);
        } else {
            // Garder la signature a cinq arguments pour les versions PHP 7.0 a 7.2.
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
        // array_key_exists conserve une valeur stockee a null distincte d'une cle absente.
        return array_key_exists($cle, $_SESSION) ? $_SESSION[$cle] : $defaut;
    }

    public function ecrire(string $cle, $valeur)
    {
        $_SESSION[$cle] = $valeur;
    }

    private function requeteSecurisee(): bool
    {
        // Un cookie Secure n'est envoye que sur HTTPS ; le parametre du
        // constructeur permet de remplacer cette detection si un proxy termine TLS.
        return isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}
