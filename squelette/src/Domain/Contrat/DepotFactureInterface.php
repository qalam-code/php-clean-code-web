<?php
declare(strict_types=1);

namespace App\Exemple\Domain\Contrat;

use App\Exemple\Domain\Entite\Facture;
use PhpCleanCode\Domain\Contrat\DepotInterface;

/**
 * Ce dont le domaine a besoin en matiere de factures. Rien de plus.
 *
 * LE CONTRAT EST ECRIT PAR CELUI QUI CONSOMME, PAS PAR CELUI QUI IMPLEMENTE.
 * C'est l'inversion de dependance : cette interface vit dans le domaine,
 * l'implementation SQL vit dans l'infrastructure, et la fleche va donc de
 * l'infrastructure vers le domaine -- jamais l'inverse.
 *
 * Le vocabulaire le montre : "impayeesDe(abonne)", pas "select(where)". Si
 * les methodes de vos depots ressemblent a du SQL traduit, la base a
 * remonte dans le domaine.
 *
 * @package App\Exemple\Domain\Contrat
 */
interface DepotFactureInterface extends DepotInterface
{
    /** @return Facture|null null si aucune facture ne porte ce numero. */
    public function trouverParNumero(string $numero): ?Facture;

    /** @return array<int,Facture> */
    public function impayeesDe(int $abonneId): array;
}

