<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use UnexpectedValueException;

/** Route des chemins vers des actions qui produisent du HTML. */
final class RouteurWeb
{
    private $routes;

    /** @param array<int,array{chemin:string,methodes:array<int,string>,action:callable}> $routes */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function servir(Requete $requete): ReponseWeb
    {
        $cheminDemande = $this->normaliserChemin($requete->chemin());
        $methodesAutorisees = [];
        foreach ($this->routes as $route) {
            if (!isset($route['chemin'], $route['methodes'], $route['action'])
                || !is_string($route['chemin'])
                || !is_array($route['methodes'])
                || !is_callable($route['action'])
            ) {
                throw new UnexpectedValueException('Definition de route invalide.');
            }

            $parametres = $this->correspondance($route['chemin'], $cheminDemande);
            if ($parametres === null) {
                continue;
            }
            $methodes = [];
            foreach ($route['methodes'] as $methode) {
                if (!is_string($methode)) {
                    throw new UnexpectedValueException('Les methodes HTTP doivent etre des chaines.');
                }
                $methodes[] = strtoupper($methode);
            }
            $methodesAutorisees = array_merge($methodesAutorisees, $methodes);
            if ($methodes === [] || in_array($requete->methode(), $methodes, true)) {
                $reponse = call_user_func($route['action'], $requete, $parametres);
                if (!$reponse instanceof ReponseWeb) {
                    throw new UnexpectedValueException('Une route web doit retourner une ReponseWeb.');
                }
                return $reponse;
            }
        }
        if ($methodesAutorisees !== []) {
            $methodesAutorisees = array_values(array_unique($methodesAutorisees));
            return new ReponseHtml(405, '<h1>405 - Methode non autorisee</h1>', [
                'Allow' => implode(', ', $methodesAutorisees),
            ]);
        }
        return new ReponseHtml(404, '<h1>404 - Page introuvable</h1>');
    }

    /** @return array<string,string>|null */
    private function correspondance(string $modele, string $chemin)
    {
        $modele = $this->normaliserChemin($modele);
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $modele, $balises, PREG_OFFSET_CAPTURE);
        $modeleSansBalises = preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '', $modele);
        if (strpos($modeleSansBalises, '{') !== false || strpos($modeleSansBalises, '}') !== false) {
            throw new UnexpectedValueException('Parametre de route invalide : ' . $modele);
        }

        $expression = '';
        $noms = [];
        $position = 0;
        foreach ($balises[0] as $index => $balise) {
            $debut = $balise[1];
            $nom = $balises[1][$index][0];
            if (in_array($nom, $noms, true)) {
                throw new UnexpectedValueException('Nom de parametre de route duplique : ' . $nom);
            }
            $expression .= preg_quote(substr($modele, $position, $debut - $position), '~') . '([^/]+)';
            $noms[] = $nom;
            $position = $debut + strlen($balise[0]);
        }
        $expression .= preg_quote(substr($modele, $position), '~');

        if (!preg_match('~\A' . $expression . '\z~', $chemin, $captures)) {
            return null;
        }
        $parametres = [];
        foreach ($noms as $index => $nom) {
            $valeur = rawurldecode($captures[$index + 1]);
            if (strpos($valeur, '/') !== false) {
                return null;
            }
            $parametres[$nom] = $valeur;
        }
        return $parametres;
    }

    private function normaliserChemin(string $chemin): string
    {
        $chemin = '/' . trim($chemin, '/');
        return $chemin === '//' ? '/' : $chemin;
    }
}