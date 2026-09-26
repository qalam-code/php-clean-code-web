<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PhpCleanCode\Application\Port\JetonInterface;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * JWT signe en HS256, sans dependance externe.
 *
 * Trois segments en base64url separes par des points : entete, charge,
 * signature. La signature couvre "entete.charge" ; changer un octet de l'un
 * ou de l'autre l'invalide.
 *
 * TROIS PIEGES, TOUS EVITES ICI :
 *
 * 1. L'algorithme annonce par le jeton n'est jamais consulte. Une
 *    bibliotheque qui lit "alg" dans l'entete accepte un jeton disant
 *    "alg":"none" -- c'est-a-dire non signe. HS256 est impose.
 *
 * 2. La comparaison de signature passe par hash_equals(). Un "===" rend son
 *    verdict d'autant plus vite que les chaines different tot, ce qui suffit
 *    a reconstituer une signature valide octet par octet.
 *
 * 3. LA CHARGE N'EST PAS UN SECRET. Elle est encodee, pas chiffree :
 *    n'importe qui la lit. N'y mettez ni mot de passe, ni donnee personnelle.
 *
 * @package PhpCleanCode\Infrastructure
 */
final class JetonJwt implements JetonInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        // Un secret vide signe tout de meme, silencieusement, et tous les
        // jetons deviennent forgeables. Mieux vaut echouer au demarrage.
        if ($secret === '') {
            throw new \InvalidArgumentException('JetonJwt : secret vide');
        }
        $this->secret = $secret;
    }

    public function emettre(array $charge, int $dureeSecondes): string
    {
        $maintenant = time();
        $charge['iat'] = $maintenant;
        $charge['exp'] = $maintenant + $dureeSecondes;

        $enteteJson = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $corpsJson  = json_encode($charge);
        if ($enteteJson === false || $corpsJson === false) {
            throw new \InvalidArgumentException('charge du jeton non serialisable');
        }

        $entete = $this->encoder($enteteJson);
        $corps  = $this->encoder($corpsJson);

        return $entete . '.' . $corps . '.' . $this->signer($entete . '.' . $corps);
    }

    public function verifier(string $jeton): array
    {
        $segments = explode('.', $jeton);
        if (count($segments) !== 3) {
            throw ErreurMetier::jetonMalforme();
        }
        list($entete, $corps, $signature) = $segments;

        if (!hash_equals($this->signer($entete . '.' . $corps), $signature)) {
            throw ErreurMetier::jetonMalSigne();
        }

        $charge = json_decode((string) $this->decoder($corps), true);
        if (!is_array($charge)) {
            throw ErreurMetier::jetonMalforme();
        }

        // Un jeton sans "exp" ne perime jamais : on le refuse plutot que de
        // lui accorder une validite perpetuelle.
        if (!isset($charge['exp']) || !is_numeric($charge['exp'])) {
            throw ErreurMetier::jetonMalforme();
        }
        if (time() >= (int) $charge['exp']) {
            throw ErreurMetier::jetonExpire();
        }

        return $charge;
    }

    private function signer(string $donnees): string
    {
        return $this->encoder(hash_hmac('sha256', $donnees, $this->secret, true));
    }

    /** base64url : alphabet URL, sans remplissage. */
    private function encoder(string $brut): string
    {
        return rtrim(strtr(base64_encode($brut), '+/', '-_'), '=');
    }

    /** @return string chaine vide si l'entree n'est pas du base64url valide. */
    private function decoder(string $encode): string
    {
        $complete = strtr($encode, '-_', '+/');
        $reste    = strlen($complete) % 4;
        if ($reste !== 0) {
            $complete .= str_repeat('=', 4 - $reste);
        }
        $brut = base64_decode($complete, true);
        return $brut === false ? '' : $brut;
    }
}

