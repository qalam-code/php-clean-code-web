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
    private $routeur;
    private $secours;
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
        $journaliseur = null
    ) {
        $this->routeur      = $routeur;
        $this->secours      = $secours;
        $this->journaliseur = $journaliseur;
    }

    public function servir(Requete $requete): ReponseHttp
    {
        try {
            $route = $this->routeur->resoudre($requete->chemin());
        } catch (Throwable $e) {
            $this->journaliser($e);
            return $this->panneSecurisee($this->secours);
        }
        if ($route === null) {
            return $this->rendre(function () use ($requete) {
                return $this->secours->traduire(
                    ErreurMetier::ressourceIntrouvable('endpoint ' . $requete->chemin())
                );
            }, $this->secours);
        }
        // Construit AVANT le try : sans presentateur, rien ne peut etre
        // rendu correctement. S'il echoue lui-meme, le secours prend le
        // relais -- il n'a, lui, aucune dependance.
        try {
            $fabriquePresentateur = $route['presentateur'];
            $presentateur         = $fabriquePresentateur();
            if (!$presentateur instanceof PresentateurAbstrait) {
                throw new \UnexpectedValueException('Le presentateur de route est invalide.');
            }
        } catch (Throwable $e) {
            $this->journaliser($e);
            return $this->panneSecurisee($this->secours);
        }

        if ($route['methodes'] !== [] && !in_array($requete->methode(), $route['methodes'], true)) {
            return $this->rendre(function () use ($presentateur, $route) {
                return $presentateur->methodeNonAutorisee($route['methodes']);
            }, $presentateur);
        }

        try {
            if ($requete->corpsInvalide()) {
                return $this->rendre(function () use ($presentateur) {
                    return $presentateur->corpsInvalide();
                }, $presentateur);
            }
            $fabriqueAction = $route['action'];
            $action         = $fabriqueAction();
            $reponse = $action($requete);
            if (!$reponse instanceof ReponseHttp) {
                throw new \UnexpectedValueException('L action de route doit rendre une ReponseHttp.');
            }
            return $reponse;
        } catch (ErreurMetier $e) {
            // Attendu : le domaine a dit non. Pas un incident.
            return $this->rendre(function () use ($presentateur, $e) {
                return $presentateur->traduire($e);
            }, $presentateur);
        } catch (Throwable $e) {
            // Inattendu : personne n'avait prevu ce cas.
            $this->journaliser($e);
            return $this->panneSecurisee($presentateur);
        }
    }

    /** Execute une traduction publique et retombe sur une panne sure si elle echoue. */
    private function rendre(callable $traduire, PresentateurAbstrait $presentateur): ReponseHttp
    {
        try {
            $reponse = $traduire();
            if (!$reponse instanceof ReponseHttp) {
                throw new \UnexpectedValueException('Un presentateur doit rendre une ReponseHttp.');
            }
            return $reponse;
        } catch (Throwable $e) {
            $this->journaliser($e);
            return $this->panneSecurisee($presentateur);
        }
    }

    /** Le presentateur de secours peut aussi echouer : le dernier recours est fixe. */
    private function panneSecurisee(PresentateurAbstrait $presentateur): ReponseHttp
    {
        $cibles = [$presentateur];
        if ($presentateur !== $this->secours) {
            $cibles[] = $this->secours;
        }
        foreach ($cibles as $cible) {
            try {
                $reponse = $cible->panne();
                if ($reponse instanceof ReponseHttp) {
                    return $reponse;
                }
                throw new \UnexpectedValueException('La panne doit etre rendue en ReponseHttp.');
            } catch (Throwable $e) {
                $this->journaliser($e);
            }
        }
        return new ReponseHttp(500, ['statut' => 'erreur', 'message' => 'erreur interne']);
    }

    private function journaliser(Throwable $e){
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

