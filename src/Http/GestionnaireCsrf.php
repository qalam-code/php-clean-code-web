<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;

/** Jeton CSRF synchronise avec la session du navigateur. */
final class GestionnaireCsrf
{
    private const CLE_SESSION = '_qalam_code_web_csrf';
    private $cleFormulaire;
    private $nomEntete;
    private $session;

    public function __construct(
        StockageSession $session,
        string $cleFormulaire = '_csrf',
        string $nomEntete = 'X-CSRF-Token'
    ) {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $cleFormulaire)) {
            throw new InvalidArgumentException('Nom de champ CSRF invalide.');
        }
        if (!preg_match('/\A[A-Za-z][A-Za-z0-9-]*\z/', $nomEntete)) {
            throw new InvalidArgumentException('Nom d entete CSRF invalide.');
        }
        $this->session = $session;
        $this->cleFormulaire = $cleFormulaire;
        $this->nomEntete = $nomEntete;
    }

    /** Jeton aleatoire stable pour la session courante. */
    public function jeton(): string
    {
        $jeton = $this->session->lire(self::CLE_SESSION);
        if (!is_string($jeton) || !preg_match('/\A[a-f0-9]{64}\z/', $jeton)) {
            $jeton = bin2hex(random_bytes(32));
            $this->session->ecrire(self::CLE_SESSION, $jeton);
        }
        return $jeton;
    }

    public function cleFormulaire(): string
    {
        return $this->cleFormulaire;
    }

    /** Valide le champ du corps ou un en-tete AJAX ; la query string est ignoree. */
    public function valider(Requete $requete): bool
    {
        $donnees = $requete->corps();
        if (array_key_exists($this->cleFormulaire, $donnees)) {
            $fourni = $donnees[$this->cleFormulaire];
        } else {
            $fourni = $requete->entete($this->nomEntete);
        }
        $attendu = $this->session->lire(self::CLE_SESSION);
        if (!is_string($attendu) || !is_string($fourni)
            || !preg_match('/\A[a-f0-9]{64}\z/', $fourni)
        ) {
            return false;
        }
        return hash_equals($attendu, $fourni);
    }
}