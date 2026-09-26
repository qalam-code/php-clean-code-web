<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PDO;
use PDOStatement;

/**
 * Socle commun des depots SQL.
 *
 * Ne fournit pas de CRUD : une classe mere qui offrirait find(), save() et
 * delete() imposerait une table par entite et une cle primaire nommee "id",
 * ce qui est faux des la premiere base heritee. Elle fournit ce qui se repete
 * vraiment -- preparer, executer, ramener une ligne ou un scalaire -- et rien
 * de plus.
 *
 * La connexion arrive par la fabrique, jamais par un PDO deja ouvert : c'est
 * ce qui garde la paresse jusqu'a la premiere requete reellement executee.
 *
 * @package PhpCleanCode\Infrastructure
 */
abstract class DepotPdo
{
    private FabriqueConnexion $connexion;

    public function __construct(FabriqueConnexion $connexion)
    {
        $this->connexion = $connexion;
    }

    final protected function pdo(): PDO
    {
        return $this->connexion->pdo();
    }

    /**
     * TOUJOURS PASSER PAR $parametres. Concatener une valeur dans $sql, meme
     * "sure" parce qu'elle vient d'un entier, est la porte d'entree des
     * injections SQL.
     *
     * @param array<string,mixed> $parametres
     */
    final protected function executer(string $sql, array $parametres = []): PDOStatement
    {
        $requete = $this->pdo()->prepare($sql);
        $requete->execute($parametres);
        return $requete;
    }

    /**
     * @param array<string,mixed> $parametres
     * @return array<string,mixed>|null
     */
    final protected function uneLigne(string $sql, array $parametres = []): ?array
    {
        $ligne = $this->executer($sql, $parametres)->fetch();
        return $ligne === false ? null : $ligne;
    }

    /**
     * @param array<string,mixed> $parametres
     * @return array<int,array<string,mixed>>
     */
    final protected function lignes(string $sql, array $parametres = []): array
    {
        return $this->executer($sql, $parametres)->fetchAll();
    }

    /**
     * @param array<string,mixed> $parametres
     * @return mixed null si aucune ligne.
     */
    final protected function scalaire(string $sql, array $parametres = [])
    {
        $valeur = $this->executer($sql, $parametres)->fetchColumn();
        return $valeur === false ? null : $valeur;
    }
}

