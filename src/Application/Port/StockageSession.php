<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Application\Port;

/** Port de stockage des valeurs de session utilisees par le web. */
interface StockageSession
{
    public function lire(string $cle, $defaut = null);

    public function ecrire(string $cle, $valeur);
}