<?php
declare(strict_types=1);

namespace PhpCleanCode\Http;

use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\Presentateur\PresentateurAbstrait;
use PhpCleanCode\Presentation\ReponseHttp;
use Throwable;

/**
 * Point d'entree unique : une requete entre, une reponse sort.
 *
 * AUCUNE EXCEPTION NE SORT D'ICI. Le "catch (Throwable)" final n'est pas un
 * tapis sous lequel on pousse les erreurs : c'est la promesse qu'un
 * consommateur recevra toujours du JSON et un code HTTP, jamais une page
 * blanche ni une trace PHP revelant les chemins du serveur.
 *
 * Ce que le catch ne doit pas devenir : un endroit ou l'erreur disparait.
 * D'ou le journaliseur -- ce qui n'est pas rendu au consommateur doit etre
 * ecrit quelque part, sinon l'incident est invisible.
 *
 * LA CONSTRUCTION DU CONTROLEUR EST DANS LE TRY, deliberement : un cablage
 * qui echoue (base injoignable, configuration absente) est un incident comme
 * un autre, et doit etre rendu par le presentateur de la route appelee.
 *
 * @package PhpCleanCode\Http
 */
final class Aiguillage
{
    private Routeur $routeur;
    private PresentateurAbstrait $secours;
    /** @var callable(Throwable): void|null */
    private $journaliseur;

    /**
     * @param PresentateurAbstrait $secours rend les erreurs qui ne
     *        concernent aucune route : chemin inconnu et pannes survenues
     *        avant qu'une route soit identifiee.
     * @param callable(Throwable): void|null $journaliseur appele pour tout ce
     *        qui n'est pas une ErreurMetier. NE DOIT PAS LEVER.
     */
    public function __construct(
        Routeur $routeur,
        PresentateurAbstrait $secours,
        ?callable $journaliseur = null
    ) {
        $this->routeur      = $routeur;
        $this->secours      = $secours;
        $this->journaliseur = $journaliseur;
    }

    public function servir(Requete $requete): ReponseHttp
    {
        $route = $this->routeur->resoudre($requete->chemin());
        if ($route === null) {
            return $this->secours->traduire(
                ErreurMetier::ressourceIntrouvable('endpoint ' . $requete->chemin())
            );
        }
        // Construit AVANT le try : sans presentateur, rien ne peut etre
        // rendu correctement. S'il echoue lui-meme, le secours prend le
        // relais -- il n'a, lui, aucune dependance.
        try {
            $fabriquePresentateur = $route['presentateur'];
            $presentateur         = $fabriquePresentateur();
        } catch (Throwable $e) {
            $this->journaliser($e);
            return $this->secours->panne();
        }

        if ($route['methodes'] !== [] && !in_array($requete->methode(), $route['methodes'], true)) {
            return $presentateur->methodeNonAutorisee($route['methodes']);
        }

        try {
            $fabriqueAction = $route['action'];
            $action         = $fabriqueAction();
            return $action($requete);
        } catch (ErreurMetier $e) {
            // Attendu : le domaine a dit non. Pas un incident.
            return $presentateur->traduire($e);
        } catch (Throwable $e) {
            // Inattendu : personne n'avait prevu ce cas.
            $this->journaliser($e);
            return $presentateur->panne();
        }
    }

    private function journaliser(Throwable $e): void
    {
        if ($this->journaliseur === null) {
            return;
        }
        try {
            $journaliseur = $this->journaliseur;
            $journaliseur($e);
        } catch (Throwable $ignore) {
            // Un journal qui tombe ne doit pas emporter la reponse avec lui.
        }
    }
}

