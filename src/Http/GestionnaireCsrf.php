<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;

/**
 * Cree et valide un jeton CSRF lie a la session du navigateur.
 * Le jeton est rendu a l'application pour ses formulaires ou ses appels AJAX ;
 * il ne doit pas etre place dans une URL.
 * Le navigateur renvoie automatiquement son cookie de session ; le jeton
 * ajoute une valeur que le site tiers qui provoque la requete ne peut pas lire.
 */
final class GestionnaireCsrf
{
    // Cle interne stable, distincte du nom public du champ de formulaire.
    private static $cleSession = '_qalam_code_web_csrf';
    private $cleFormulaire;
    private $nomEntete;
    // L'interface de stockage garde ce composant indépendant de l'implémentation des sessions PHP.
    private $session;

    public function __construct(
        StockageSession $session,
        string $cleFormulaire = '_csrf',
        string $nomEntete = 'X-CSRF-Token'
    ) {
        // Les noms sont limites a des caracteres simples avant de les utiliser
        // pour lire le corps HTTP ou un en-tete.
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

    /**
     * Retourne le jeton de la session courante, en le creant au premier acces.
     * Le meme jeton reste utilise pendant cette session afin que le formulaire
     * affiche et le POST suivant puissent etre compares.
     */
    public function jeton(): string
    {
        $jeton = $this->session->lire(self::$cleSession);
        if (!is_string($jeton) || !preg_match('/\A[a-f0-9]{64}\z/', $jeton)) {
            // random_bytes fournit un secret imprévisible ; 32 octets deviennent 64 caractères hexadécimaux.
            $jeton = bin2hex(random_bytes(32));
            $this->session->ecrire(self::$cleSession, $jeton);
        }
        return $jeton;
    }

    public function cleFormulaire(): string
    {
        return $this->cleFormulaire;
    }

    /**
     * Compare le jeton soumis avec celui garde dans la session.
     * Les formulaires utilisent le champ configure ; les clients AJAX peuvent
     * envoyer l'en-tete configure. Le jeton n'est jamais accepte depuis l'URL.
     */
    public function valider(Requete $requete): bool
    {
        // Le corps et les en-têtes viennent du client : ils sont considérés comme non fiables.
        $donnees = $requete->corps();
        // Si le champ de formulaire existe, il est prioritaire ; l'en-tete
        // sert de solution de remplacement quand ce champ n'est pas present.
        if (array_key_exists($this->cleFormulaire, $donnees)) {
            $fourni = $donnees[$this->cleFormulaire];
        } else {
            $fourni = $requete->entete($this->nomEntete);
        }
        $attendu = $this->session->lire(self::$cleSession);
        if (!is_string($attendu) || !is_string($fourni)
            || !preg_match('/\A[a-f0-9]{64}\z/', $fourni)
        ) {
            return false;
        }
        // Comparaison sure pour un secret : ne pas utiliser l'egalite stricte.
        return hash_equals($attendu, $fourni);
    }
}
