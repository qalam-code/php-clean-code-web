<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PhpCleanCode\Application\Port\JournalInterface;
use Throwable;

/**
 * Journal en base, qui ne fait jamais tomber l'appelant.
 *
 * NE LEVE PAS, PAR CONTRAT (voir JournalInterface). Le catch ci-dessous n'est
 * donc pas une exception avalee par negligence : c'est la traduction promise
 * d'un echec d'ecriture en "false", pour que l'operation metier deja
 * accomplie ne soit pas annulee par sa propre trace.
 *
 * La contrepartie doit etre assumee : si le cas d'usage ignore ce booleen,
 * une table de journal pleine ou verrouillee se traduit par une perte de
 * traces totalement silencieuse. Le cas d'usage doit dire ce qu'il en fait.
 *
 * @package PhpCleanCode\Infrastructure
 */
final class JournalPdo extends DepotPdo implements JournalInterface
{
    private $table;

    public function __construct(FabriqueConnexion $connexion, string $table = 'journal')
    {
        parent::__construct($connexion);
        $this->table = $table;
    }

    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool
    {
        try {
            $this->executer(
                'INSERT INTO `' . $this->table . '` (acteur_id, action, detail, horodatage)'
                . ' VALUES (:acteur, :action, :detail, NOW())',
                [':acteur' => $acteurId, ':action' => $action, ':detail' => $detail]
            );
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

