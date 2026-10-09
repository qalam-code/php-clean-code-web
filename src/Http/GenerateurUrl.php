<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use OutOfBoundsException;

/** Construit les chemins des routes nommées sans les recopier dans l'application. */
final class GenerateurUrl
{
    private $routesNommees = [];

    /** @param array<int,array<string,mixed>> $routes routes déjà préfixées, y compris les groupes développés */
    public function __construct(array $routes)
    {
        foreach ($routes as $route) {
            if (!is_array($route) || !isset($route['nom'])) {
                continue;
            }
            if (!is_string($route['nom']) || $route['nom'] === '' || !isset($route['chemin']) || !is_string($route['chemin'])) {
                throw new InvalidArgumentException('Une route nommee doit avoir un nom et un chemin textuels.');
            }
            if (array_key_exists($route['nom'], $this->routesNommees)) {
                throw new InvalidArgumentException('Le nom de route est deja utilise : ' . $route['nom']);
            }
            if (array_key_exists('contraintes', $route) && !is_array($route['contraintes'])) {
                throw new InvalidArgumentException('Les contraintes d une route nommee doivent etre un tableau.');
            }
            $this->routesNommees[$route['nom']] = [
                'chemin' => $route['chemin'],
                'contraintes' => isset($route['contraintes']) ? $route['contraintes'] : [],
            ];
        }
    }

    /**
     * Retourne le chemin d'une route. Les valeurs dynamiques sont encodées
     * comme des segments et ne peuvent pas introduire une nouvelle barre.
     *
     * @param array<string,string|int> $parametres paramètres du chemin
     * @param array<string,scalar|null> $requete paramètres de query string
     */
    public function pour(string $nom, array $parametres = [], array $requete = []): string
    {
        if (!array_key_exists($nom, $this->routesNommees)) {
            throw new OutOfBoundsException('Route nommee introuvable : ' . $nom);
        }

        $definition = $this->routesNommees[$nom];
        $chemin = $definition['chemin'];
        $contraintes = $definition['contraintes'];
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $chemin, $balises, PREG_OFFSET_CAPTURE);
        $cheminSansBalises = preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '', $chemin);
        if (strpos($cheminSansBalises, '{') !== false || strpos($cheminSansBalises, '}') !== false) {
            throw new InvalidArgumentException('Parametre invalide dans le chemin de la route : ' . $chemin);
        }

        $resultat = '';
        $position = 0;
        $nomsUtilises = [];
        foreach ($balises[0] as $index => $balise) {
            $nomParametre = $balises[1][$index][0];
            if (in_array($nomParametre, $nomsUtilises, true)) {
                throw new InvalidArgumentException('Nom de parametre duplique dans la route : ' . $nomParametre);
            }
            if (!array_key_exists($nomParametre, $parametres)) {
                throw new InvalidArgumentException('Parametre manquant pour la route : ' . $nomParametre);
            }

            $valeur = $parametres[$nomParametre];
            if ((!is_string($valeur) && !is_int($valeur)) || (string) $valeur === '' || strpos((string) $valeur, '/') !== false) {
                throw new InvalidArgumentException('Un parametre de route doit etre une chaine ou un entier non vide, sans barre oblique.');
            }
            if (array_key_exists($nomParametre, $contraintes)) {
                $contrainte = $contraintes[$nomParametre];
                if (!is_string($contrainte) || $contrainte === '') {
                    throw new InvalidArgumentException('La contrainte doit etre une expression reguliere non vide.');
                }
                $expressionContrainte = '~\\A(?:' . $contrainte . ')\\z~';
                if (@preg_match($expressionContrainte, '') === false) {
                    throw new InvalidArgumentException('Expression reguliere invalide pour le parametre : ' . $nomParametre);
                }
                if (preg_match($expressionContrainte, (string) $valeur) !== 1) {
                    throw new InvalidArgumentException('Le parametre ne respecte pas la contrainte de la route : ' . $nomParametre);
                }
            }

            $debut = $balise[1];
            $resultat .= substr($chemin, $position, $debut - $position) . rawurlencode((string) $valeur);
            $position = $debut + strlen($balise[0]);
            $nomsUtilises[] = $nomParametre;
        }
        $resultat .= substr($chemin, $position);

        $parametresInconnus = array_diff(array_keys($parametres), $nomsUtilises);
        if ($parametresInconnus !== []) {
            throw new InvalidArgumentException('Parametre non utilise pour la route : ' . reset($parametresInconnus));
        }
        $contraintesInconnues = array_diff(array_keys($contraintes), $nomsUtilises);
        if ($contraintesInconnues !== []) {
            throw new InvalidArgumentException('Contrainte sans parametre correspondant : ' . reset($contraintesInconnues));
        }

        foreach ($requete as $cle => $valeur) {
            if ((!is_string($cle) && !is_int($cle))
                || (is_array($valeur) || is_object($valeur) || is_resource($valeur))
            ) {
                throw new InvalidArgumentException('Les parametres de query string doivent etre des valeurs scalaires ou null.');
            }
        }
        $queryString = http_build_query($requete, '', '&', PHP_QUERY_RFC3986);

        return $queryString === '' ? $resultat : $resultat . '?' . $queryString;
    }
}
