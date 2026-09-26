<?php
declare(strict_types=1);

namespace App\Exemple;

use App\Exemple\Application\Connecter;
use App\Exemple\Application\ConsulterFacture;
use App\Exemple\Domain\Contrat\DepotFactureInterface;
use App\Exemple\Domain\Contrat\DepotUtilisateurInterface;
use App\Exemple\Infrastructure\DepotFacturePdo;
use App\Exemple\Infrastructure\DepotUtilisateurPdo;
use App\Exemple\Infrastructure\ResolveurDepot;
use App\Exemple\Presentation\Controleur\ControleurConnexion;
use App\Exemple\Presentation\Controleur\ControleurFacture;
use App\Exemple\Presentation\Presentateur\PresentateurConnexion;
use App\Exemple\Presentation\Presentateur\PresentateurFacture;
use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Application\Port\JetonInterface;
use PhpCleanCode\Application\Port\JournalInterface;
use PhpCleanCode\Fabrique as FabriqueBase;
use PhpCleanCode\Http\Routeur;
use PhpCleanCode\Infrastructure\FabriqueConnexion;
use PhpCleanCode\Infrastructure\JetonJwt;
use PhpCleanCode\Infrastructure\JournalPdo;
use PhpCleanCode\Presentation\Authentificateur;

/**
 * Racine de composition du projet.
 *
 * LISEZ CE FICHIER EN PREMIER quand vous decouvrez l'application : il dit
 * quels endpoints existent, quel cas d'usage repond a chacun, et de quoi ce
 * cas d'usage depend. Tout le reste en decoule.
 *
 * @package App\Exemple
 */
final class Fabrique extends FabriqueBase
{
    public function routeur(): Routeur
    {
        // Le prefixe correspond au dossier d'installation sous la racine web.
        // A la racine d'un domaine, passez une chaine vide.
        $routeur = new Routeur((string) (getenv('BASE_URI') ?: ''));

        // CHAQUE ROUTE DECLARE SON PROPRE PRESENTATEUR. C'est ce qui garantit
        // qu'une panne survenue pendant le cablage de /facture sera rendue
        // au format de /facture -- et non a celui d'un autre endpoint.
        //
        // Les deux entrees sont des fonctions : rien n'est construit pour
        // les routes qui ne sont pas appelees.
        $routeur->ajouter(
            'login',
            function () {
                return new ControleurConnexion($this->connecter(), new PresentateurConnexion());
            },
            function () {
                return new PresentateurConnexion();
            },
            ['POST']
        );

        $routeur->ajouter(
            'facture',
            function () {
                return new ControleurFacture($this->consulterFacture(), new PresentateurFacture());
            },
            function () {
                return new PresentateurFacture();
            },
            ['GET', 'POST']
        );

        return $routeur;
    }

    protected function decrireConnexion(): FabriqueConnexion
    {
        // Aucune connexion n'est ouverte ici : FabriqueConnexion attend le
        // premier pdo(), c'est-a-dire la premiere requete SQL reellement
        // executee.
        $hote = (string) (getenv('DB_HOTE') ?: '127.0.0.1');
        $base = (string) (getenv('DB_BASE') ?: 'exemple');

        return new FabriqueConnexion(
            'mysql:host=' . $hote . ';dbname=' . $base . ';charset=utf8mb4',
            (string) (getenv('DB_UTILISATEUR') ?: 'root'),
            (string) (getenv('DB_MOT_DE_PASSE') ?: ''),
            // FILET DE SECURITE : un "USE base" explicite. Des qu'une seule
            // requete du projet nomme une table sans prefixer sa base, c'est
            // la base par defaut du compte MySQL qui decide -- et une copie
            // de developpement se met a ecrire en production.
            $base
        );
    }

    // ---- Cas d'usage ------------------------------------------------------

    private function connecter(): Connecter
    {
        return new Connecter($this->utilisateurs(), $this->jetons(), $this->journal());
    }

    private function consulterFacture(): ConsulterFacture
    {
        return new ConsulterFacture($this->authentification(), $this->factures());
    }

    // ---- Services ---------------------------------------------------------

    private function authentification(): AuthentificationInterface
    {
        return $this->partage('authentification', function () {
            // DIFFEREE, ET C'EST INDISPENSABLE. Construire l'authentificateur
            // exige un depot, donc la base. Sans ce report, une base
            // injoignable ferait repondre "erreur interne" a une requete sans
            // jeton, au lieu de "token introuvable".
            return $this->authentificationDifferee(function () {
                return new Authentificateur(
                    $this->requete(),
                    $this->jetons(),
                    new ResolveurDepot($this->utilisateurs())
                );
            });
        });
    }

    private function jetons(): JetonInterface
    {
        return $this->partage('jetons', function () {
            $secret = (string) getenv('JWT_SECRET');
            // PAS DE SECRET PAR DEFAUT. Une valeur de repli -- "secret",
            // "changeme" -- finit toujours en production, et alors n'importe
            // qui peut forger un jeton valide. Mieux vaut refuser de demarrer.
            if ($secret === '') {
                throw new \RuntimeException('JWT_SECRET absent de la configuration');
            }
            return new JetonJwt($secret);
        });
    }

    private function journal(): JournalInterface
    {
        return $this->partage('journal', function () {
            return new JournalPdo($this->connexion(), 'journal');
        });
    }

    private function utilisateurs(): DepotUtilisateurInterface
    {
        return $this->partage('utilisateurs', function () {
            return new DepotUtilisateurPdo($this->connexion());
        });
    }

    private function factures(): DepotFactureInterface
    {
        return $this->partage('factures', function () {
            return new DepotFacturePdo($this->connexion());
        });
    }
}

