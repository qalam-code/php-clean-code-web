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
 * comment les services sont cables et de quoi chaque cas d'usage depend.
 * La table des routes HTTP est dans routes/api.php.
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

        // Les definitions de routes restent declaratives. Cette table de
        // services relie leurs noms aux fabriques concretes, sans construire
        // controleurs ni presentateurs avant qu'une route soit appelee.
        $services = [
            'connexion' => [
                'action' => function () {
                    return new ControleurConnexion($this->connecter(), new PresentateurConnexion());
                },
                'presentateur' => function () {
                    return new PresentateurConnexion();
                },
            ],
            'facture' => [
                'action' => function () {
                    return new ControleurFacture($this->consulterFacture(), new PresentateurFacture());
                },
                'presentateur' => function () {
                    return new PresentateurFacture();
                },
            ],
        ];

        $definitions = require dirname(__DIR__) . '/routes/api.php';
        foreach ($definitions as $definition) {
            if (!isset($definition['chemin'], $definition['service'], $definition['methodes'])) {
                throw new \InvalidArgumentException('Definition de route incomplete.');
            }
            if (!isset($services[$definition['service']])) {
                throw new \InvalidArgumentException('Service de route inconnu.');
            }

            $service = $services[$definition['service']];
            $routeur->ajouter(
                $definition['chemin'],
                $service['action'],
                $service['presentateur'],
                $definition['methodes']
            );
        }

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

