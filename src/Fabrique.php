<?php
declare(strict_types=1);

namespace PhpCleanCode;

use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Application\Port\SurveillanceInterface;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Http\Routeur;
use PhpCleanCode\Infrastructure\AuthentificationDifferee;
use PhpCleanCode\Infrastructure\FabriqueConnexion;
use PhpCleanCode\Infrastructure\SurveillanceTimeout;

/**
 * Racine de composition : l'endroit ou les implementations concretes sont
 * assemblees et reliees aux contrats de l'application.
 *
 * Les cas d'usage recoivent leurs dependances et ne fabriquent pas leurs
 * adaptateurs d'infrastructure. Cette discipline a un cout d'ecriture --
 * cette classe grossit -- et un benefice qu'aucune autre technique ne donne :
 * tout le reste du code devient
 * testable sans base, sans reseau et sans serveur, parce qu'il suffit de lui
 * passer autre chose.
 *
 * Lisez cette classe comme le plan du systeme : qui depend de qui y est
 * ecrit noir sur blanc, en un seul fichier.
 *
 * DEUX REGLES A NE PAS ENFREINDRE :
 *
 * 1. RIEN ICI N'OUVRE DE CONNEXION NI N'APPELLE LE RESEAU. On assemble des
 *    objets, on ne travaille pas. FabriqueConnexion et
 *    AuthentificationDifferee sont la pour ca.
 *
 * 2. AUCUN CAS D'USAGE NE CONSTRUIT D'INFRASTRUCTURE. Le jour ou un depot
 *    fait "new PDO" dans un coin, la racine de composition ment, et le test
 *    qui croyait travailler en memoire attaque la base de production.
 *
 * @package PhpCleanCode
 */
abstract class Fabrique
{
    private $requete;
    private $connexion = null;
    /** @var array<string,mixed> */
    private $partages = [];

    public function __construct(Requete $requete)
    {
        $this->requete = $requete;
    }

    /** La table des routes de l'application. */
    abstract public function routeur(): Routeur;

    /**
     * Decrit la connexion -- SANS L'OUVRIR. FabriqueConnexion se connecte au
     * premier pdo(), c'est-a-dire a la premiere requete reellement executee.
     */
    abstract protected function decrireConnexion(): FabriqueConnexion;

    final public function requete(): Requete
    {
        return $this->requete;
    }

    final public function connexion(): FabriqueConnexion
    {
        if ($this->connexion === null) {
            $this->connexion = $this->decrireConnexion();
        }
        return $this->connexion;
    }

    public function surveillance(): SurveillanceInterface
    {
        return $this->partage('surveillance', function () {
            // Reglee sous max_execution_time, avec de la marge pour terminer
            // proprement au lieu d'etre interrompu au milieu d'une ecriture.
            $limite = (float) ini_get('max_execution_time');
            return new SurveillanceTimeout($limite > 0 ? $limite : 30.0, 5.0);
        });
    }

    /**
     * Memoise. Deux cas d'usage cables dans la meme requete partagent alors
     * le meme depot, donc la meme connexion -- et non deux.
     *
     * @param callable(): mixed $construire
     * @return mixed
     */
    final protected function partage(string $cle, callable $construire)
    {
        if (!array_key_exists($cle, $this->partages)) {
            $this->partages[$cle] = $construire();
        }
        return $this->partages[$cle];
    }

    /**
     * Enveloppe l'authentification pour qu'elle ne soit construite qu'au
     * moment ou un cas d'usage demande vraiment qui appelle.
     *
     * @param callable(): AuthentificationInterface $construire
     */
    final protected function authentificationDifferee(callable $construire): AuthentificationInterface
    {
        return new AuthentificationDifferee($construire);
    }
}

