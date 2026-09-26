<?php
declare(strict_types=1);

namespace App\Exemple\Domain;

use PhpCleanCode\Domain\ErreurMetier;

/**
 * Les erreurs propres a CE projet.
 *
 * Elle herite des types communs -- jeton, parametre manquant, echec
 * d'ecriture -- et n'ajoute que ce que la facturation sait dire de plus.
 *
 * Remarquez qu'il n'y a pas de code HTTP ici. Le domaine ignore HTTP ; c'est
 * le presentateur qui decide qu'une facture introuvable vaut 404.
 *
 * @package App\Exemple\Domain
 */
final class ErreurFacturation extends ErreurMetier
{
    const FACTURE_INTROUVABLE = 'facture_introuvable';
    const FACTURE_DEJA_PAYEE  = 'facture_deja_payee';

    public static function factureIntrouvable(string $numero): self
    {
        // Le numero va dans le detail, donc au journal -- pas forcement dans
        // la reponse. Le presentateur tranchera.
        return new self(self::FACTURE_INTROUVABLE, 'facture ' . $numero);
    }

    public static function factureDejaPayee(string $numero): self
    {
        return new self(self::FACTURE_DEJA_PAYEE, 'facture ' . $numero);
    }
}

