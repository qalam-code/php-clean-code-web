<?php
declare(strict_types=1);

namespace PhpCleanCode;

/**
 * Conteneur explicite de fabriques de services.
 *
 * Il n'instancie rien par reflexion : la racine de composition enregistre une
 * fabrique pour chaque service. La resolution est differee et, par defaut,
 * une instance est partagee pendant la duree de vie de ce conteneur.
 *
 * @package PhpCleanCode
 */
final class Conteneur
{
    /** @var array<string,array{fabrique:callable,partagee:bool}> */
    private $definitions = [];

    /** @var array<string,mixed> */
    private $instances = [];

    /** @var array<string,bool> */
    private $enConstruction = [];

    /**
     * Enregistre une fabrique explicite.
     *
     * La fabrique peut recevoir ce conteneur pour resoudre ses dependances.
     * Ce conteneur doit rester dans la racine de composition ; ne le transmettez
     * pas aux controleurs ni aux cas d'usage.
     *
     * @param callable(Conteneur): mixed $fabrique
     */
    public function definir(string $identifiant, callable $fabrique, bool $partagee = true)
    {
        if (array_key_exists($identifiant, $this->definitions)) {
            throw new \InvalidArgumentException('Service deja defini : ' . $identifiant);
        }

        $this->definitions[$identifiant] = [
            'fabrique' => $fabrique,
            'partagee' => $partagee,
        ];
    }

    public function contient(string $identifiant): bool
    {
        return array_key_exists($identifiant, $this->definitions);
    }

    /** Resout un service, en le construisant seulement a son premier usage. */
    public function obtenir(string $identifiant)
    {
        if (!$this->contient($identifiant)) {
            throw new \InvalidArgumentException('Service inconnu : ' . $identifiant);
        }

        $definition = $this->definitions[$identifiant];
        if ($definition['partagee'] && array_key_exists($identifiant, $this->instances)) {
            return $this->instances[$identifiant];
        }

        if (isset($this->enConstruction[$identifiant])) {
            throw new \RuntimeException('Dependance circulaire detectee : ' . $identifiant);
        }

        $this->enConstruction[$identifiant] = true;
        try {
            $instance = call_user_func($definition['fabrique'], $this);
        } finally {
            unset($this->enConstruction[$identifiant]);
        }

        if ($definition['partagee']) {
            $this->instances[$identifiant] = $instance;
        }

        return $instance;
    }
}
