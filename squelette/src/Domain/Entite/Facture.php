<?php
declare(strict_types=1);

namespace App\Exemple\Domain\Entite;

/**
 * Une facture.
 *
 * DEUX CHOSES A REMARQUER, ET ELLES VALENT POUR TOUTES VOS ENTITES.
 *
 * 1. LE MONTANT EST UN ENTIER, EN CENTIMES. Un float ne represente pas
 *    exactement 0,10 : additionnez-en assez et le total se met a finir par
 *    des chiffres impossibles. En comptabilite, cet ecart est un incident.
 *
 * 2. AUCUNE ANNOTATION, AUCUN LIEN VERS LA BASE. L'entite ignore qu'elle est
 *    stockee, et donc comment. C'est ce qui permet de changer de schema, ou
 *    de reunir deux tables en une entite, sans toucher aux cas d'usage.
 *
 * Les invariants se defendent dans le constructeur : une facture ne peut pas
 * exister sans numero. Mieux vaut echouer a la construire que la promener a
 * moitie remplie dans toute l'application.
 *
 * @package App\Exemple\Domain\Entite
 */
final class Facture
{
    const EN_ATTENTE = 'en_attente';
    const PAYEE      = 'payee';
    const ANNULEE    = 'annulee';

    private $numero;
    private $montantCentimes;
    private $statut;
    private $dateEmission;

    public function __construct(
        string $numero,
        int $montantCentimes,
        string $statut,
        string $dateEmission
    ) {
        if ($numero === '') {
            throw new \InvalidArgumentException('Facture : numero vide');
        }
        if ($montantCentimes < 0) {
            throw new \InvalidArgumentException('Facture : montant negatif');
        }
        if (!in_array($statut, [self::EN_ATTENTE, self::PAYEE, self::ANNULEE], true)) {
            throw new \InvalidArgumentException('Facture : statut inconnu');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateEmission);
        if ($date === false || $date->format('Y-m-d') !== $dateEmission) {
            throw new \InvalidArgumentException('Facture : date d emission invalide');
        }
        $this->numero          = $numero;
        $this->montantCentimes = $montantCentimes;
        $this->statut          = $statut;
        $this->dateEmission    = $dateEmission;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function montantCentimes(): int
    {
        return $this->montantCentimes;
    }

    public function statut(): string
    {
        return $this->statut;
    }

    public function dateEmission(): string
    {
        return $this->dateEmission;
    }

    /**
     * REGLE METIER, PLACEE DANS L'ENTITE. La meme question sera posee par le
     * paiement, par la relance et par l'export ; une seule reponse, ici, au
     * lieu de trois "if" qui finiront par diverger.
     */
    public function estPayable(): bool
    {
        return $this->statut === self::EN_ATTENTE;
    }

    /** Conversion d'affichage : le domaine compte en centimes, pas l'API. */
    public function montantAffiche(): string
    {
        return number_format($this->montantCentimes / 100, 2, '.', '');
    }
}

