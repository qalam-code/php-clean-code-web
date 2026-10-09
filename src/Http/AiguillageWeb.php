<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use Throwable;
use UnexpectedValueException;

/**
 * Point d'entree HTTP du framework web.
 *
 * Les requetes d'actifs (CSS/JS des vues) sont traitees en premier. Les autres
 * sont transmises au routeur fourni par la fabrique. Toute exception qui
 * franchit cette frontiere est journalisee et convertie en reponse HTML 500,
 * sans exposer son detail au visiteur.
 */
final class AiguillageWeb
{
    // Garde une fabrique plutôt qu'un routeur imposé : la composition concrète appartient à l'application.
    private $fabriqueRouteur;
    // Reçoit les erreurs internes ; il peut être remplacé par le système de journalisation de l'application.
    private $journaliseur;
    // Optionnel : l'aiguillage peut aussi être utilisé sans servir les fichiers CSS et JS des vues.
    private $serveurActifs;
    private $fabriqueErreur;
    private $cors;
    private $securite;

    /**
     * @param callable():RouteurWeb $fabriqueRouteur
     * @param callable(Throwable):void|null $journaliseur
     * @param ServeurActifsVue|null $serveurActifs
     * @param callable|null $fabriqueErreur fabrique de reponses recevant un statut et un contexte public.
     * @param CorsMiddleware|null $cors politique CORS facultative autour du traitement HTTP.
     * @param EnTetesSecuriteMiddleware|false|null $securite null active les valeurs de base, false désactive.
     *
     * La fabrique permet de construire le routeur au moment du traitement de
     * la requete. Le journaliseur et le serveur d'actifs sont facultatifs ;
     * lorsqu'aucun journaliseur n'est fourni, les erreurs vont dans le journal
     * PHP du serveur.
     */
    public function __construct(callable $fabriqueRouteur, $journaliseur = null, $serveurActifs = null, $fabriqueErreur = null, $cors = null, $securite = null)
    {
        // Ces contrôles compensent l'absence de types de propriétés plus récents que PHP 7.0.
        if ($journaliseur !== null && !is_callable($journaliseur)) {
            throw new \InvalidArgumentException('Le journaliseur doit etre appelable.');
        }
        if ($serveurActifs !== null && !($serveurActifs instanceof ServeurActifsVue)) {
            throw new \InvalidArgumentException('Le serveur d actifs doit etre un ServeurActifsVue.');
        }
        if ($fabriqueErreur !== null && !is_callable($fabriqueErreur)) {
            throw new \InvalidArgumentException('La fabrique d erreurs doit etre appelable.');
        }
        if ($cors !== null && !($cors instanceof CorsMiddleware)) {
            throw new \InvalidArgumentException('La politique CORS doit etre un CorsMiddleware.');
        }
        if ($securite !== null && $securite !== false && !($securite instanceof EnTetesSecuriteMiddleware)) {
            throw new \InvalidArgumentException('La securite doit etre un EnTetesSecuriteMiddleware, false ou null.');
        }
        $this->fabriqueRouteur = $fabriqueRouteur;
        $this->journaliseur = $journaliseur;
        $this->serveurActifs = $serveurActifs;
        $this->fabriqueErreur = $fabriqueErreur;
        $this->cors = $cors;
        // null choisit les protections par défaut ; false est le choix explicite pour les désactiver.
        $this->securite = $securite === false ? false : ($securite ?: new EnTetesSecuriteMiddleware());
    }

    public function servir(Requete $requete): ReponseWeb
    {
        try {
            // La politique CORS enveloppe toute la réponse HTTP, y compris les
            // réponses d'erreur et le précontrôle OPTIONS du routeur.
            $traitement = function () use ($requete): ReponseWeb {
                try {
                    return $this->traiterSansCors($requete);
                } catch (Throwable $erreur) {
                    $this->journaliser($erreur);
                    return $this->reponseErreurInterne();
                }
            };
            // Compose les enveloppes autour du routage : CORS, puis les en-têtes de sécurité.
            if ($this->cors !== null) {
                $suite = $traitement;
                $traitement = function () use ($requete, $suite): ReponseWeb {
                    return $this->cors->traiter($requete, $suite);
                };
            }
            if ($this->securite !== false) {
                $suite = $traitement;
                $traitement = function () use ($requete, $suite): ReponseWeb {
                    return $this->securite->traiter($requete, $suite);
                };
            }
            return $traitement();
        } catch (Throwable $erreur) {
            // Une panne interne est journalisee pour le diagnostic, mais le
            // visiteur ne reçoit qu'un message generique.
            $this->journaliser($erreur);
            return $this->reponseErreurInterne();
        }
    }

    /** Traite les actifs et les routes avant l'application des en-têtes globaux. */
    private function traiterSansCors(Requete $requete): ReponseWeb
    {
        // Les actifs ont leur propre reponse HTTP. Si le chemin ne designe
        // pas un actif connu, le serveur retourne null et le routage normal continue.
        if ($this->serveurActifs !== null) {
            $reponseActif = $this->serveurActifs->servir($requete);
            if ($reponseActif !== null) {
                return $reponseActif;
            }
        }

        // Le routeur est construit après le service possible d'un actif.
        $fabrique = $this->fabriqueRouteur;
        $routeur = $fabrique();
        if (!$routeur instanceof RouteurWeb) {
            throw new UnexpectedValueException('La fabrique doit retourner un RouteurWeb.');
        }

        $reponse = $routeur->servir($requete);
        if (!$reponse instanceof ReponseWeb) {
            throw new UnexpectedValueException('Le routeur web doit retourner une ReponseWeb.');
        }
        return $reponse;
    }

    /** L'application peut personnaliser la page 500 sans recevoir l'exception technique. */
    private function reponseErreurInterne(): ReponseWeb
    {
        $contexte = ['message' => 'Une erreur inattendue est survenue.'];
        if ($this->fabriqueErreur !== null) {
            try {
                $fabrique = $this->fabriqueErreur;
                $reponse = $fabrique(500, $contexte);
                if ($reponse instanceof ReponseWeb && $reponse->code() === 500) {
                    return $reponse;
                }
                throw new UnexpectedValueException('La fabrique d erreur doit retourner une ReponseWeb avec le statut 500.');
            } catch (Throwable $erreurFabrique) {
                error_log('Echec de la fabrique de reponse 500 : ' . $erreurFabrique->getMessage());
            }
        }

        return new ReponseHtml(
            500,
            '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur</title>'
                . '<h1>500 - Erreur interne</h1><p>Une erreur inattendue est survenue.</p></html>'
        );
    }

    private function journaliser(Throwable $erreur)
    {
        try {
            if ($this->journaliseur === null) {
                error_log((string) $erreur);
                return;
            }
            // Le journaliseur personnalise peut lui aussi echouer ; son
            // exception ne doit pas empecher l'envoi de la reponse 500.
            $journaliseur = $this->journaliseur;
            $journaliseur($erreur);
        } catch (Throwable $erreurJournal) {
            error_log('Echec du journaliseur web : ' . $erreurJournal->getMessage());
        }
    }
}
