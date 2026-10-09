<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Application\Port;

/**
 * Contrat de lecture et d'ecriture des valeurs de session.
 * Les composants web dependent de ce port, pas de $_SESSION ni de la session
 * native PHP ; l'application peut donc fournir un autre stockage. La valeur
 * par defaut ne sert que si la cle est absente, pas si elle contient null.
 */
interface StockageSession
{
    /** Retourne la valeur associee a la cle, ou $defaut si elle est absente. */
    public function lire(string $cle, $defaut = null);

    /** Enregistre ou remplace la valeur associee a la cle pour les prochaines lectures. */
    public function ecrire(string $cle, $valeur);
}
