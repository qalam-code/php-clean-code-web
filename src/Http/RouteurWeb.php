<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use UnexpectedValueException;

/**
 * Associe les chemins HTTP aux actions qui produisent les pages web.
 * Les routes sont fournies par l'application ; ce routeur les valide,
 * extrait leurs parametres et applique les controles communs avant l'action.
 */
final class RouteurWeb
{
    private $routes;
    private $csrf;
    private $fabriqueErreur;

    /**
     * @param array<int,array{chemin:string,methodes:array<int,string>,action:callable,middleware?:array<int,callable>}> $routes
     *        L'action peut notamment etre [$controleur, 'methode'].
     * @param GestionnaireCsrf|null $csrf protecteur partage par les routes
     *        qui exigent un jeton CSRF.
     * @param callable|null $fabriqueErreur fabrique de reponses recevant le statut et un contexte public.
     */
    public function __construct(array $routes, $csrf = null, $fabriqueErreur = null)
    {
        // Le routeur reçoit une configuration déjà composée par l'application ; il ne crée pas les contrôleurs lui-même.
        if ($csrf !== null && !($csrf instanceof GestionnaireCsrf)) {
            throw new \InvalidArgumentException('Le protecteur CSRF doit etre un GestionnaireCsrf.');
        }
        if ($fabriqueErreur !== null && !is_callable($fabriqueErreur)) {
            throw new \InvalidArgumentException('La fabrique d erreurs doit etre appelable.');
        }
        $this->routes = $routes;
        $this->csrf = $csrf;
        $this->fabriqueErreur = $fabriqueErreur;
    }

    public function servir(Requete $requete): ReponseWeb
    {
        // On normalise une seule fois l'URL entrante, puis on la compare aux modèles de routes.
        $cheminDemande = $this->normaliserChemin($requete->chemin());
        $methodeDemande = strtoupper($requete->methode());
        $methodesAutorisees = [];
        foreach ($this->routes as $route) {
            // Une definition incorrecte est une erreur de configuration, pas
            // une route a ignorer silencieusement.
            if (!isset($route['chemin'], $route['methodes'], $route['action'])
                || !is_string($route['chemin'])
                || !is_array($route['methodes'])
                || !is_callable($route['action'])
                || (isset($route['middleware']) && !is_array($route['middleware']))
                || (array_key_exists('contraintes', $route) && !is_array($route['contraintes']))
            ) {
                throw new UnexpectedValueException('Definition de route invalide.');
            }
            if (isset($route['middleware'])) {
                foreach ($route['middleware'] as $middleware) {
                    if (!is_callable($middleware)) {
                        throw new UnexpectedValueException('Chaque middleware de route doit etre appelable.');
                    }
                }
            }

            $contraintes = isset($route['contraintes']) ? $route['contraintes'] : [];
            $parametres = $this->correspondance($route['chemin'], $cheminDemande, $contraintes);
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
            // Une route GET accepte aussi HEAD ; les en-têtes seront conservés,
            // puis le corps sera supprimé avant de retourner la réponse.
            $methodesAvecHead = in_array('GET', $methodes, true)
                ? array_values(array_unique(array_merge($methodes, ['HEAD'])))
                : $methodes;
            $methodesAutorisees = array_merge($methodesAutorisees, $methodesAvecHead);
            if ($methodes === [] || in_array($methodeDemande, $methodesAvecHead, true)) {
                // Par defaut, les methodes qui peuvent modifier l'etat
                // exigent CSRF. Une route peut declarer explicitement
                // 'csrf' => true ou false pour adapter cette politique.
                $csrfRequis = array_key_exists('csrf', $route)
                    ? $route['csrf']
                    : !in_array($methodeDemande, ['GET', 'HEAD', 'OPTIONS'], true);
                if (!is_bool($csrfRequis)) {
                    throw new UnexpectedValueException('La configuration CSRF doit etre booleenne.');
                }
                if ($csrfRequis) {
                    if ($this->csrf === null) {
                        throw new UnexpectedValueException('Un GestionnaireCsrf est requis pour cette route.');
                    }
                    if (!$this->csrf->valider($requete)) {
                        return $this->reponseErreur(403, ['message' => 'Jeton CSRF invalide.']);
                    }
                }

                // Les middleware enveloppent l'action dans l'ordre déclaré.
                // Chacun peut poursuivre avec $suite ou retourner une réponse pour arrêter la chaîne.
                $reponse = $this->executerAction($route, $requete, $parametres);
                if ($methodeDemande === 'HEAD') {
                    return new ReponseWeb($reponse->code(), '', $reponse->entetes());
                }
                return $reponse;
            }
        }
        // Un chemin connu avec une autre methode produit 405 et annonce les
        // methodes permises. Un chemin sans correspondance produit 404.
        if ($methodesAutorisees !== []) {
            $methodesAutorisees = array_values(array_unique($methodesAutorisees));
            // OPTIONS est pris en charge automatiquement pour tout chemin reconnu,
            // sauf si l'application a déclaré sa propre route OPTIONS plus haut.
            if (!in_array('OPTIONS', $methodesAutorisees, true)) {
                $methodesAutorisees[] = 'OPTIONS';
            }
            if ($methodeDemande === 'OPTIONS') {
                return new ReponseWeb(204, '', ['Allow' => implode(', ', $methodesAutorisees)]);
            }
            return $this->reponseErreur(405, ['methodes' => $methodesAutorisees]);
        }
        return $this->reponseErreur(404);
    }

    /** Exécute l'action à travers les middleware de la route, de l'extérieur vers l'intérieur. */
    private function executerAction(array $route, Requete $requete, array $parametres): ReponseWeb
    {
        $middleware = isset($route['middleware']) ? array_values($route['middleware']) : [];

        // Cette fermeture est récursive : elle se capture elle-même par référence
        // afin que chaque appel puisse avancer vers l'élément suivant de la chaîne.
        $executer = null;
        $executer = function (int $index) use (&$executer, $middleware, $route, $requete, $parametres): ReponseWeb {
            // Cas d'arrêt de la récursion : après le dernier middleware, on exécute l'action.
            if ($index >= count($middleware)) {
                $reponse = call_user_func($route['action'], $requete, $parametres);
            } else {
                $appelSuite = false;

                // $suite() représente l'étape suivante. Quand le middleware courant
                // l'appelle, on relance la fermeture avec l'index du middleware suivant.
                $suite = function () use (&$appelSuite, &$executer, $index): ReponseWeb {
                    if ($appelSuite) {
                        throw new UnexpectedValueException('Un middleware ne peut appeler la suite qu une seule fois.');
                    }
                    $appelSuite = true;
                    return $executer($index + 1);
                };

                // Le middleware peut appeler $suite(), puis examiner ou modifier
                // la réponse au retour, ou retourner directement sa propre réponse.
                $reponse = call_user_func($middleware[$index], $requete, $parametres, $suite);
            }

            if (!$reponse instanceof ReponseWeb) {
                throw new UnexpectedValueException('Une action et chaque middleware doivent retourner une ReponseWeb.');
            }
            return $reponse;
        };

        return $executer(0);
    }

    /** Construit une erreur HTTP sans fournir de details internes a la fabrique de l'application. */
    private function reponseErreur(int $code, array $contexte = []): ReponseWeb
    {
        if ($this->fabriqueErreur !== null) {
            $fabrique = $this->fabriqueErreur;
            $reponse = $fabrique($code, $contexte);
            if (!$reponse instanceof ReponseWeb || $reponse->code() !== $code) {
                throw new UnexpectedValueException('La fabrique d erreur doit retourner une ReponseWeb avec le statut demande.');
            }
            return $reponse;
        }

        if ($code === 403) {
            return new ReponseHtml(403, '<h1>403 - Requete refusee</h1><p>Jeton CSRF invalide.</p>');
        }
        if ($code === 405) {
            return new ReponseHtml(405, '<h1>405 - Methode non autorisee</h1>', [
                'Allow' => implode(', ', $contexte['methodes']),
            ]);
        }
        return new ReponseHtml(404, '<h1>404 - Page introuvable</h1>');
    }

    /**
     * Compare un modele (ex. /bonjour/{nom}) au chemin demande.
     * Les morceaux litteraux sont proteges comme texte regulier ; chaque
     * parametre ne capture qu'un segment, jamais une barre oblique.
     *
     * @return array<string,string>|null valeurs decodees par nom, ou null si
     *         le chemin ne correspond pas.
     */
    private function correspondance(string $modele, string $chemin, array $contraintes = [])
    {
        $modele = $this->normaliserChemin($modele);
        // On repère d'abord les paramètres pour transformer le modèle en expression régulière.
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
        foreach ($contraintes as $nom => $contrainte) {
            if (!is_string($nom) || !in_array($nom, $noms, true) || !is_string($contrainte) || $contrainte === '') {
                throw new UnexpectedValueException('Une contrainte doit viser un parametre de route et contenir une expression reguliere.');
            }
            // Les contraintes sont des expressions PCRE sans delimiters. On les
            // verifie avant de les appliquer aux valeurs fournies par le client.
            if (@preg_match('~\A(?:' . $contrainte . ')\z~', '') === false) {
                throw new UnexpectedValueException('Expression reguliere invalide pour le parametre : ' . $nom);
            }
        }
        // Ancrer l'expression des deux cotes exige une correspondance avec
        // tout le chemin, pas seulement avec l'un de ses prefixes.
        $expression .= preg_quote(substr($modele, $position), '~');
        if (!preg_match('~\A' . $expression . '\z~', $chemin, $captures)) {
            return null;
        }
        $parametres = [];
        foreach ($noms as $index => $nom) {
            // Le chemin fournit des segments encodés en URL : on remet leur valeur lisible à l'action.
            $valeur = rawurldecode($captures[$index + 1]);
            // Refuser aussi les slashs encodes apres decodage : un parametre
            // ne peut pas s'echapper de son segment de route.
            if (strpos($valeur, '/') !== false) {
                return null;
            }
            if (isset($contraintes[$nom]) && preg_match('~\A(?:' . $contraintes[$nom] . ')\z~', $valeur) !== 1) {
                return null;
            }
            $parametres[$nom] = $valeur;
        }
        return $parametres;
    }

    private function normaliserChemin(string $chemin): string
    {
        $chemin = '/' . trim($chemin, '/');
        // Tous les chemins sont representes avec une barre initiale ; le
        // chemin racine conserve donc la forme '/'.
        return $chemin === '//' ? '/' : $chemin;
    }
}
