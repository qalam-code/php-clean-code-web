<?php
declare(strict_types=1);

namespace PhpCleanCode\Application\Port;

/**
 * Trace des actions.
 *
 * LA SIGNATURE EST UN BOOLEEN, PAS UNE EXCEPTION, ET C'EST DELIBERE.
 *
 * Un journal qui leve une exception fait echouer l'operation qu'il devait
 * seulement accompagner : la transaction est passee, l'argent a bouge, et le
 * consommateur recoit une erreur parce que l'ecriture du journal a echoue.
 *
 * Retourner false laisse l'appelant decider. Un cas d'usage ou la trace est
 * une obligation reglementaire traduira false en erreur ; ailleurs, il
 * l'ignorera. Dans les deux cas le choix est visible dans le cas d'usage, et
 * non enfoui dans une implementation.
 *
 * @package PhpCleanCode\Application\Port
 */
interface JournalInterface
{
    /**
     * @return bool false si la trace n'a pas pu etre ecrite. NE LEVE JAMAIS.
     */
    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool;
}


