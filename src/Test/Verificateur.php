<?php
declare(strict_types=1);

namespace PhpCleanCode\Test;

use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\ReponseHttp;
use Throwable;

/**
 * Verificateur d'equivalence : compare ce que le code rend a ce qu'il doit
 * rendre, et sort avec un code d'erreur au premier ecart.
 *
 * POURQUOI PAS PHPUNIT ? Parce que ce harnais sert a une chose que PHPUnit
 * rend penible : tourner sur la MEME version de PHP que la production, sur le
 * serveur, sans composer install. Une suite qui verifie qu'un refactoring n'a
 * rien casse doit pouvoir s'executer la ou le code s'executera vraiment. Les
 * versions recentes de PHPUnit exigent PHP 8 ; un projet en 7.0 ne peut
 * simplement pas les installer.
 *
 * Ce n'est pas un remplacant de PHPUnit pour un projet neuf. C'est l'outil
 * du refactoring d'un code ancien.
 *
 * L'ECART EST TOUJOURS AFFICHE EN ENTIER, attendu puis obtenu. Un harnais
 * qui se contente de "echec" oblige a rouvrir le code pour comprendre ; celui
 * -ci doit permettre de conclure en lisant la sortie.
 *
 * @package PhpCleanCode\Test
 */
final class Verificateur
{
    private $ok = 0;
    private $ko = 0;
    /** @var array<int,string> */
    private $echecs = [];

    public function section(string $titre){
        echo PHP_EOL . $titre . PHP_EOL . str_repeat('-', 72) . PHP_EOL;
    }

    /**
     * COMPARAISON STRICTE, TOUJOURS. Avec "==", "0" egale "" et 200 egale
     * "200" : exactement les confusions qu'un controle de contrat doit
     * attraper, puisque le JSON rendu au consommateur, lui, distingue.
     *
     * @param mixed $attendu
     * @param mixed $obtenu
     */
    public function egal($attendu, $obtenu, string $libelle){
        if ($attendu === $obtenu) {
            $this->reussite($libelle);
            return;
        }
        $this->echec($libelle, $this->rendre($attendu), $this->rendre($obtenu));
    }

    /** @param mixed $valeur */
    public function vrai($valeur, string $libelle){
        $this->egal(true, $valeur === true, $libelle);
    }

    /**
     * Verifie d'un coup le code HTTP, les cles rendues ET LEUR ORDRE.
     *
     * L'ordre compte : il fait partie de ce que lisent des consommateurs
     * ecrits a la main, et rien d'autre dans une suite de tests ne le
     * surveille.
     *
     * @param array<string,mixed> $corpsAttendu
     */
    public function reponseEgale(
        int $codeAttendu,
        array $corpsAttendu,
        ReponseHttp $obtenue,
        string $libelle
    ){
        $this->egal($codeAttendu, $obtenue->code(), $libelle . ' -- code HTTP');
        $this->egal(
            array_keys($corpsAttendu),
            array_keys($obtenue->corps()),
            $libelle . ' -- cles et ordre'
        );
        $this->egal($corpsAttendu, $obtenue->corps(), $libelle . ' -- contenu');
    }

    /**
     * Verifie qu'un appel leve bien une ErreurMetier DU TYPE attendu.
     *
     * Attraper "une exception quelconque" laisserait passer une erreur de
     * frappe sur un nom de methode : le test resterait vert en verifiant que
     * le code plante.
     *
     * @param callable(): mixed $appel
     */
    public function leve(string $typeAttendu, callable $appel, string $libelle){
        try {
            $appel();
        } catch (ErreurMetier $e) {
            $this->egal($typeAttendu, $e->type(), $libelle);
            return;
        } catch (Throwable $e) {
            $this->echec($libelle, $typeAttendu, get_class($e) . ' : ' . $e->getMessage());
            return;
        }
        $this->echec($libelle, $typeAttendu, 'aucune exception levee');
    }

    /** @return int 0 si tout est conforme, 1 sinon -- a passer a exit(). */
    public function bilan(): int
    {
        echo PHP_EOL . str_repeat('=', 72) . PHP_EOL;
        echo 'Conforme : ' . $this->ok . '    Divergent : ' . $this->ko . PHP_EOL;
        foreach ($this->echecs as $echec) {
            echo '  - ' . $echec . PHP_EOL;
        }
        echo str_repeat('=', 72) . PHP_EOL;
        return $this->ko > 0 ? 1 : 0;
    }

    private function reussite(string $libelle){
        $this->ok++;
        echo '  OK     ' . $libelle . PHP_EOL;
    }

    private function echec(string $libelle, string $attendu, string $obtenu){
        $this->ko++;
        $this->echecs[] = $libelle;
        echo '  ECHEC  ' . $libelle . PHP_EOL;
        echo '           attendu : ' . $attendu . PHP_EOL;
        echo '           obtenu  : ' . $obtenu . PHP_EOL;
    }

    /** @param mixed $valeur */
    private function rendre($valeur): string
    {
        if (is_bool($valeur)) {
            return $valeur ? 'true' : 'false';
        }
        if ($valeur === null) {
            return 'null';
        }
        $json = (string) json_encode($valeur, JSON_UNESCAPED_UNICODE);
        return strlen($json) > 400 ? substr($json, 0, 400) . '...' : $json;
    }
}

