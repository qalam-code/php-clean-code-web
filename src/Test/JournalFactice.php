<?php
declare(strict_types=1);

namespace PhpCleanCode\Test;

use PhpCleanCode\Application\Port\JournalInterface;

/**
 * Journal en memoire : retient les traces au lieu de les ecrire.
 *
 * AVERTISSEMENT QUI VAUT POUR TOUS LES DOUBLES DE CE DOSSIER.
 *
 * Un double qui se comporte autrement que l'objet reel rend les tests verts
 * et la production fausse. Le cas vecu : un double de journal levait une
 * exception en cas d'echec, le cas d'usage avait donc un "catch" et un
 * message d'erreur dedie, teste et conforme -- alors que le depot reel
 * avalait l'exception et ne levait jamais. Ce message d'erreur n'a jamais pu
 * apparaitre en production. Une branche entiere de code, testee, morte.
 *
 * REGLE : avant d'ecrire un double, relisez l'implementation reelle. Ici,
 * enregistrer() retourne false et NE LEVE PAS -- comme JournalPdo.
 *
 * @package PhpCleanCode\Test
 */
final class JournalFactice implements JournalInterface
{
    /** @var array<int,array{acteur:int,action:string,detail:string}> */
    public array $traces = [];

    /** Mettre a false pour simuler une table pleine ou verrouillee. */
    public bool $reussit = true;

    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool
    {
        if (!$this->reussit) {
            return false;
        }
        $this->traces[] = ['acteur' => $acteurId, 'action' => $action, 'detail' => $detail];
        return true;
    }

    public function compte(): int
    {
        return count($this->traces);
    }

    public function derniere(): ?array
    {
        return $this->traces === [] ? null : $this->traces[count($this->traces) - 1];
    }
}

