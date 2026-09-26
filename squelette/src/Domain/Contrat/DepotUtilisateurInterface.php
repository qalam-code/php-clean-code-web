<?php
declare(strict_types=1);

namespace App\Exemple\Domain\Contrat;

use PhpCleanCode\Domain\Contrat\DepotInterface;
use PhpCleanCode\Domain\Entite\Identite;

/**
 * Acces aux comptes.
 *
 * LA VERIFICATION DU MOT DE PASSE EST DANS L'IMPLEMENTATION, pas ici : le
 * domaine demande "qui est-ce, avec ces identifiants ?" et ne veut connaitre
 * ni l'algorithme de hachage, ni la forme de la table.
 *
 * Aucune methode ne rend le mot de passe, meme hache. Ce qui ne sort pas du
 * depot ne peut pas se retrouver dans un journal ou une reponse.
 *
 * @package App\Exemple\Domain\Contrat
 */
interface DepotUtilisateurInterface extends DepotInterface
{
    /** @return Identite|null null si le couple est refuse. */
    public function parIdentifiants(string $identifiant, string $motDePasse): ?Identite;

    /** @return Identite|null null si le compte n'existe plus ou est desactive. */
    public function parId(int $id): ?Identite;
}

