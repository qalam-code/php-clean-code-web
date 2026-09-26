<?php
declare(strict_types=1);

namespace PhpCleanCode\Domain\Contrat;

/**
 * MARQUEUR. N'IMPOSE AUCUNE METHODE, ET C'EST VOULU.
 *
 * Un depot se decrit par le besoin du domaine, pas par une API generique :
 *
 *     interface DepotFactureInterface extends DepotInterface
 *     {
 *         public function trouverParNumero(string $numero);
 *         public function impayeesDe(int $abonneId): array;
 *     }
 *
 * Une interface commune a find/save/delete ferait fuiter la base de donnees
 * dans le domaine : le cas d'usage se mettrait a raisonner en lignes et en
 * cles primaires au lieu de factures et d'abonnes, et toute optimisation SQL
 * deviendrait impossible sans changer le contrat.
 *
 * Ce marqueur ne sert donc qu'a une chose : rendre les depots reperables,
 * pour un controle d'architecture ou un scan d'autochargement.
 *
 * @package PhpCleanCode\Domain\Contrat
 */
interface DepotInterface
{
}

