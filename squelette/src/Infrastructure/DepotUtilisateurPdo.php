<?php
declare(strict_types=1);

namespace App\Exemple\Infrastructure;

use App\Exemple\Domain\Contrat\DepotUtilisateurInterface;
use PhpCleanCode\Domain\Entite\Identite;
use PhpCleanCode\Infrastructure\DepotPdo;

/**
 * Comptes en base.
 *
 * DEUX POINTS DE SECURITE, ET AUCUN N'EST NEGOCIABLE.
 *
 * 1. LE MOT DE PASSE N'EST JAMAIS COMPARE EN SQL. Un "WHERE mot_de_passe =
 *    :mdp" suppose un stockage en clair ou un hachage reversible, et confie
 *    la comparaison au moteur. On charge le hachage, et password_verify --
 *    qui compare en temps constant -- tranche.
 *
 * 2. LE COMPTE DESACTIVE EST FILTRE DANS parId(). C'est ce qui rend un jeton
 *    encore valide inoperant des la desactivation, au lieu d'attendre son
 *    expiration.
 *
 * @package App\Exemple\Infrastructure
 */
final class DepotUtilisateurPdo extends DepotPdo implements DepotUtilisateurInterface
{
    public function parIdentifiants(string $identifiant, string $motDePasse){
        $ligne = $this->uneLigne(
            'SELECT id, nom, mot_de_passe FROM utilisateurs'
            . ' WHERE identifiant = :identifiant AND actif = 1 LIMIT 1',
            [':identifiant' => $identifiant]
        );

        if ($ligne === null) {
            // On verifie tout de meme un hachage factice : sans cela, un
            // compte inexistant repond nettement plus vite qu'un mot de
            // passe faux, ce qui suffit a enumerer les comptes.
            password_verify($motDePasse, '$2y$10$' . str_repeat('0', 53));
            return null;
        }

        if (!password_verify($motDePasse, (string) $ligne['mot_de_passe'])) {
            return null;
        }

        return new Identite((int) $ligne['id'], (string) $ligne['nom']);
    }

    public function parId(int $id){
        $ligne = $this->uneLigne(
            'SELECT id, nom FROM utilisateurs WHERE id = :id AND actif = 1 LIMIT 1',
            [':id' => $id]
        );
        return $ligne === null ? null : new Identite((int) $ligne['id'], (string) $ligne['nom']);
    }
}

