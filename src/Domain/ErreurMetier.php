<?php
declare(strict_types=1);

namespace PhpCleanCode\Domain;

use Exception;

/**
 * Erreur metier : une seule exception, qualifiee par un TYPE.
 *
 * Le principe, et il n'y en a qu'un : le domaine ne connait pas HTTP. Il dit
 * ce qui ne va pas -- "parametre manquant", "ressource introuvable" -- et
 * c'est le presentateur, tout en haut, qui traduit ce type en code et en
 * message. Changer un libelle ne touche donc jamais le domaine, et un meme
 * type peut se traduire differemment selon l'endpoint.
 *
 * Une classe d'exception par cas produirait vingt classes vides et autant de
 * "catch" a tenir a jour. Un type suffit.
 *
 * VOTRE PROJET ETEND CETTE CLASSE. Les constantes ci-dessous sont celles que
 * tout projet rencontre ; ajoutez les votres dans la sous-classe :
 *
 *     final class ErreurFacturation extends ErreurMetier
 *     {
 *         const FACTURE_DEJA_PAYEE = 'facture_deja_payee';
 *
 *         public static function factureDejaPayee(string $numero): self
 *         {
 *             return new self(self::FACTURE_DEJA_PAYEE, 'facture ' . $numero);
 *         }
 *     }
 *
 * @package PhpCleanCode\Domain
 */
class ErreurMetier extends Exception
{
    /** Entree absente ou vide. */
    const PARAMETRE_MANQUANT = 'parametre_manquant';
    /** Ce qui est demande n'existe pas. */
    const RESSOURCE_INTROUVABLE = 'ressource_introuvable';
    /** Une ecriture a echoue. Le detail technique est dans getMessage(). */
    const ECHEC_ECRITURE = 'echec_ecriture';

    // ---- Authentification -------------------------------------------------
    const IDENTIFIANTS_INVALIDES = 'identifiants_invalides';
    const JETON_ABSENT           = 'jeton_absent';
    const JETON_MALFORME         = 'jeton_malforme';
    const JETON_MAL_SIGNE        = 'jeton_mal_signe';
    const JETON_EXPIRE           = 'jeton_expire';
    /** Jeton valide, mais aucun utilisateur connu derriere. */
    const ACTEUR_INTROUVABLE     = 'acteur_introuvable';
    const ECHEC_JOURNALISATION   = 'echec_journalisation';

    private string $type;

    /**
     * PUBLIC PAR OBLIGATION, PAS PAR INTENTION.
     *
     * Les fabriques nommees sont la bonne facon de construire une erreur :
     * elles seules garantissent qu'un type connu va avec le bon message. Ce
     * constructeur devrait donc etre prive.
     *
     * PHP l'interdit : une methode qui en redefinit une autre ne peut pas en
     * reduire la visibilite, et Exception::__construct() est publique. Le
     * declarer prive fait echouer la classe entiere a se declarer :
     *
     *     Fatal error: Access level to ErreurMetier::__construct()
     *     must be public (as in class Exception)
     *
     * PHP 8 a relache la regle pour les constructeurs. Ne vous y fiez pas :
     * le code doit se declarer sous la version la plus ancienne que vous
     * visez, et outils/compat.php est la pour le verifier.
     */
    public function __construct(string $type, string $detail = '')
    {
        parent::__construct($detail);
        $this->type = $type;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function estDeType(string $type): bool
    {
        return $this->type === $type;
    }

    // ---- Fabriques nommees ------------------------------------------------

    public static function parametreManquant(string $parametres): self
    {
        return new static(self::PARAMETRE_MANQUANT, 'parametre manquant : ' . $parametres);
    }

    public static function ressourceIntrouvable(string $quoi): self
    {
        return new static(self::RESSOURCE_INTROUVABLE, $quoi);
    }

    /** @param string $detail message technique -- NE JAMAIS l'exposer tel quel. */
    public static function echecEcriture(string $detail): self
    {
        return new static(self::ECHEC_ECRITURE, $detail);
    }

    public static function identifiantsInvalides(): self
    {
        return new static(self::IDENTIFIANTS_INVALIDES, 'couple identifiant / mot de passe refuse');
    }

    public static function jetonAbsent(): self
    {
        return new static(self::JETON_ABSENT, 'aucun jeton dans la requete');
    }

    public static function jetonMalforme(): self
    {
        return new static(self::JETON_MALFORME, 'jeton illisible');
    }

    public static function jetonMalSigne(): self
    {
        return new static(self::JETON_MAL_SIGNE, 'signature invalide');
    }

    public static function jetonExpire(): self
    {
        return new static(self::JETON_EXPIRE, 'jeton expire');
    }

    public static function acteurIntrouvable(): self
    {
        return new static(self::ACTEUR_INTROUVABLE, 'aucun utilisateur connu pour ce jeton');
    }

    public static function echecJournalisation(): self
    {
        return new static(self::ECHEC_JOURNALISATION, 'echec de journalisation');
    }
}

