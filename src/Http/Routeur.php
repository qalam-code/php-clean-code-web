<?php
declare(strict_types=1);

namespace PhpCleanCode\Http;

use PhpCleanCode\Presentation\Presentateur\PresentateurAbstrait;

/**
 * Table des routes. Ne fait que resoudre : c'est Aiguillage qui execute.
 *
 * CHAQUE ROUTE PORTE SON PROPRE PRESENTATEUR, et c'est la decision de
 * conception la plus importante de cette classe.
 *
 * Avec un presentateur unique pour toute l'application, une panne survenue
 * pendant le cablage d'un endpoint est rendue par le format d'un autre. Vu en
 * production : une base injoignable repondait "identifiants invalides" a
 * chaque appel, parce que le presentateur de secours etait celui de la
 * connexion. Les consommateurs ont cherche du cote de leurs identifiants
 * pendant que le probleme etait ailleurs.
 *
 * Les deux entrees sont des FABRIQUES, pas des objets : rien n'est construit
 * pour les routes qui ne sont pas appelees.
 *
 * @package PhpCleanCode\Http
 */
final class Routeur
{
    /** @var array<string,array{action:callable,presentateur:callable,methodes:array<int,string>}> */
    private array $routes = [];
    private string $base;

    /**
     * @param string $base prefixe d'installation a retirer du chemin,
     *        par exemple "/api_paiement" si l'API n'est pas a la racine.
     */
    public function __construct(string $base = '')
    {
        $this->base = '/' . trim($base, '/');
    }

    /**
     * @param callable(): mixed $action fabrique du controleur, ou
     *        directement l'action ; elle recoit la Requete et rend une
     *        ReponseHttp.
     * @param callable(): PresentateurAbstrait $presentateur fabrique du
     *        presentateur de CETTE route -- celui qui rendra aussi bien ses
     *        erreurs metier que ses pannes.
     * @param array<int,string> $methodes vide = toutes les methodes.
     */
    public function ajouter(
        string $chemin,
        callable $action,
        callable $presentateur,
        array $methodes = []
    ): void {
        $this->routes[$this->normaliser($chemin)] = [
            'action'       => $action,
            'presentateur' => $presentateur,
            'methodes'     => array_map('strtoupper', $methodes),
        ];
    }

    /**
     * @return array{action:callable,presentateur:callable,methodes:array<int,string>}|null
     */
    public function resoudre(string $chemin): ?array
    {
        return $this->routes[$this->normaliser($chemin)] ?? null;
    }

    /** @return array<int,string> les chemins declares, utile a un test d'inventaire. */
    public function chemins(): array
    {
        return array_keys($this->routes);
    }

    /**
     * "/api/Paye-Facture/" et "paye-facture" designent la meme route. La
     * casse est ignoree : un consommateur qui ecrit "/Login" recevrait sinon
     * un 404 incomprehensible.
     */
    private function normaliser(string $chemin): string
    {
        $chemin = (string) parse_url($chemin, PHP_URL_PATH);
        // Le prefixe doit occuper un segment entier : /api ne doit pas
        // transformer /apix/facture en /x/facture.
        if ($this->base !== '/' && ($chemin === $this->base || strpos($chemin, $this->base . '/') === 0)) {
            $chemin = substr($chemin, strlen($this->base));
        }
        return strtolower(trim($chemin, '/'));
    }
}
