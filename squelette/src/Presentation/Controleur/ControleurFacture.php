<?php
declare(strict_types=1);

namespace App\Exemple\Presentation\Controleur;

use App\Exemple\Application\ConsulterFacture;
use App\Exemple\Presentation\Presentateur\PresentateurFacture;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Adaptateur de /facture.
 *
 * @package App\Exemple\Presentation\Controleur
 */
final class ControleurFacture
{
    private $consulter;
    private $presentateur;

    public function __construct(ConsulterFacture $consulter, PresentateurFacture $presentateur)
    {
        $this->consulter    = $consulter;
        $this->presentateur = $presentateur;
    }

    public function __invoke(Requete $requete): ReponseHttp
    {
        $facture = $this->consulter->executer((string) $requete->parametre('numero', ''));

        return $this->presentateur->facture($facture);
    }
}

