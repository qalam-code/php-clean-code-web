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

    /**
     * @param array<int,array{chemin:string,methodes:array<int,string>,action:callable}> $routes
     * @param GestionnaireCsrf|null $csrf protecteur partage par les routes
     *        qui exigent un jeton CSRF.
     */
    public function __construct(array $routes, $csrf = null)
    {
        // Le routeur reçoit une configuration déjà composée par l'application ; il ne crée pas les contrôleurs lui-même.
        if ($csrf !== null && !($csrf instanceof GestionnaireCsrf)) {
            throw new \InvalidArgumentException('Le protecteur CSRF doit etre un GestionnaireCsrf.');
        }
        $this->routes = $routes;
        $this->csrf = $csrf;
    }

    public function servir(Requete $requete): ReponseWeb
    {
        // On normalise une seule fois l'URL entrante, puis on la compare aux modèles de routes.
        $cheminDemande = $this->normaliserChemin($requete->chemin());
        $methodesAutorisees = [];
        foreach ($this->routes as $route) {
            // Une definition incorrecte est une erreur de configuration, pas
            // une route a ignorer silencieusement.
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
                // Par defaut, les methodes qui peuvent modifier l'etat
                // exigent CSRF. Une route peut declarer explicitement
                // 'csrf' => true ou false pour adapter cette politique.
                $csrfRequis = array_key_exists('csrf', $route)
                    ? $route['csrf']
                    : !in_array($requete->methode(), ['GET', 'HEAD', 'OPTIONS'], true);
                if (!is_bool($csrfRequis)) {
                    throw new UnexpectedValueException('La configuration CSRF doit etre booleenne.');
                }
                if ($csrfRequis) {
                    if ($this->csrf === null) {
                        throw new UnexpectedValueException('Un GestionnaireCsrf est requis pour cette route.');
                    }
                    if (!$this->csrf->valider($requete)) {
                        return new ReponseHtml(403, '<h1>403 - Requete refusee</h1><p>Jeton CSRF invalide.</p>');
                    }
                }

                // L'action recoit la requete et les parametres extraits du
                // chemin. Elle doit retourner une reponse web complete.
                $reponse = call_user_func($route['action'], $requete, $parametres);
                if (!$reponse instanceof ReponseWeb) {
                    throw new UnexpectedValueException('Une route web doit retourner une ReponseWeb.');
                }
                // Appliquer POST-Redirect-GET aux formulaires HTML reussis :
                // un rafraichissement rechargera le GET au lieu de renvoyer le POST.
                // Les erreurs HTML (ex. 422) restent sur place pour afficher
                // les messages de validation ; les reponses JSON/AJAX ne changent pas.
                if ($requete->methode() === 'POST'
                    && $reponse instanceof ReponseHtml
                    && $reponse->code() >= 200
                    && $reponse->code() < 300
                ) {
                    return new ReponseWeb(303, '', [
                        'Location' => $requete->chemin(),
                        'Cache-Control' => 'no-store',
                    ]);
                }
                return $reponse;
            }
        }
        // Un chemin connu avec une autre methode produit 405 et annonce les
        // methodes permises. Un chemin sans correspondance produit 404.
        if ($methodesAutorisees !== []) {
            $methodesAutorisees = array_values(array_unique($methodesAutorisees));
            return new ReponseHtml(405, '<h1>405 - Methode non autorisee</h1>', [
                'Allow' => implode(', ', $methodesAutorisees),
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
    private function correspondance(string $modele, string $chemin)
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
