<?php
declare(strict_types=1);

namespace App\Exemple\Infrastructure;

use App\Exemple\Domain\Contrat\DepotUtilisateurInterface;
use PhpCleanCode\Application\Port\ResolveurActeurInterface;
use PhpCleanCode\Domain\Entite\Identite;

/**
 * De la charge du jeton vers le compte reel.
 *
 * Classe de cinq lignes, et pourtant c'est elle qui fait la difference entre
 * "le jeton dit que c'est l'utilisateur 42" et "l'utilisateur 42 existe
 * toujours et est actif".
 *
 * La tentation permanente est de la court-circuiter -- l'identifiant est
 * dans le jeton, pourquoi relire la base ? Parce qu'un jeton emis ce matin
 * parle d'un compte tel qu'il etait ce matin.
 *
 * @package App\Exemple\Infrastructure
 */
final class ResolveurDepot implements ResolveurActeurInterface
{
    private DepotUtilisateurInterface $utilisateurs;

    public function __construct(DepotUtilisateurInterface $utilisateurs)
    {
        $this->utilisateurs = $utilisateurs;
    }

    public function resoudre(array $charge): ?Identite
    {
        if (!isset($charge['sub']) || !is_numeric($charge['sub'])) {
            return null;
        }
        return $this->utilisateurs->parId((int) $charge['sub']);
    }
}

