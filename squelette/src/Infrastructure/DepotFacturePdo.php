<?php
declare(strict_types=1);

namespace App\Exemple\Infrastructure;

use App\Exemple\Domain\Contrat\DepotFactureInterface;
use App\Exemple\Domain\Entite\Facture;
use PhpCleanCode\Infrastructure\DepotPdo;

/**
 * Traduction SQL du contrat DepotFactureInterface.
 *
 * TOUT LE SQL DU PROJET VIT DANS CETTE COUCHE. Un cas d'usage qui contient
 * un SELECT ne peut plus etre teste sans base -- et c'est toujours par la
 * qu'une architecture propre commence a se defaire.
 *
 * La methode hydrater() est la frontiere : au-dessus d'elle on parle en
 * Facture, en dessous en lignes de table. Elle convertit aussi les types,
 * puisque PDO rend des chaines meme pour les colonnes numeriques.
 *
 * @package App\Exemple\Infrastructure
 */
final class DepotFacturePdo extends DepotPdo implements DepotFactureInterface
{
    public function trouverParNumero(string $numero){
        $ligne = $this->uneLigne(
            'SELECT numero, montant_centimes, statut, date_emission'
            . ' FROM factures WHERE numero = :numero LIMIT 1',
            [':numero' => $numero]
        );
        return $ligne === null ? null : $this->hydrater($ligne);
    }

    public function impayeesDe(int $abonneId): array
    {
        $lignes = $this->lignes(
            'SELECT numero, montant_centimes, statut, date_emission'
            . ' FROM factures WHERE abonne_id = :abonne AND statut = :statut'
            . ' ORDER BY date_emission DESC',
            [':abonne' => $abonneId, ':statut' => Facture::EN_ATTENTE]
        );

        return array_map([$this, 'hydrater'], $lignes);
    }

    /** @param array<string,mixed> $ligne */
    private function hydrater(array $ligne): Facture
    {
        return new Facture(
            (string) $ligne['numero'],
            (int) $ligne['montant_centimes'],
            (string) $ligne['statut'],
            (string) $ligne['date_emission']
        );
    }
}

