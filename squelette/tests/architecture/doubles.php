<?php
declare(strict_types=1);

/**
 * Doubles propres au projet.
 *
 * Les doubles generiques -- journal, surveillance, jeton, authentification --
 * viennent de la bibliotheque (PhpCleanCode\Test). Ici, seuls les depots du
 * domaine.
 *
 * RAPPEL : un double doit se comporter comme l'objet reel, y compris dans ses
 * defauts. Un double plus strict ou plus bavard que l'implementation rend les
 * tests verts sur du code mort.
 */

use App\Exemple\Domain\Contrat\DepotFactureInterface;
use App\Exemple\Domain\Contrat\DepotUtilisateurInterface;
use App\Exemple\Domain\Entite\Facture;
use PhpCleanCode\Domain\Entite\Identite;

final class DepotFactureFactice implements DepotFactureInterface
{
    /** @var array<string,Facture> */
    private $parNumero = [];
    public $appels = 0;

    public function ajouter(Facture $facture){
        $this->parNumero[$facture->numero()] = $facture;
    }

    public function trouverParNumero(string $numero){
        $this->appels++;
        return $this->parNumero[$numero] ?? null;
    }

    public function impayeesDe(int $abonneId): array
    {
        $impayees = [];
        foreach ($this->parNumero as $facture) {
            if ($facture->estPayable()) {
                $impayees[] = $facture;
            }
        }
        return $impayees;
    }
}

final class DepotUtilisateurFactice implements DepotUtilisateurInterface
{
    /** @var array<int,array{identite:Identite,motDePasse:string}> */
    private $comptes = [];

    public function ajouter(int $id, string $nom, string $motDePasse){
        $this->comptes[$id] = [
            'identite'   => new Identite($id, $nom),
            'motDePasse' => $motDePasse,
        ];
    }

    public function parIdentifiants(string $identifiant, string $motDePasse){
        foreach ($this->comptes as $compte) {
            if ($compte['identite']->nom() === $identifiant
                && $compte['motDePasse'] === $motDePasse) {
                return $compte['identite'];
            }
        }
        return null;
    }

    public function parId(int $id){
        return isset($this->comptes[$id]) ? $this->comptes[$id]['identite'] : null;
    }

    /** Simule une desactivation : le jeton reste valide, le compte non. */
    public function desactiver(int $id){
        unset($this->comptes[$id]);
    }
}

