<?php
declare(strict_types=1);

namespace PhpCleanCode\Test;

use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Domain\Entite\Identite;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * Identite decidee d'avance -- ou refus decide d'avance.
 *
 * Permet de tester un cas d'usage sans fabriquer de jeton valide. Les deux
 * situations doivent etre couvertes : celle ou l'appelant est connu, et celle
 * ou l'authentification echoue. La seconde est la plus souvent oubliee, et
 * c'est elle qui decide si un endpoint protege l'est vraiment.
 *
 * @package PhpCleanCode\Test
 */
final class AuthentificationFactice implements AuthentificationInterface
{
    private ?Identite $identite;
    private ?ErreurMetier $refus;
    public int $appels = 0;

    private function __construct(?Identite $identite, ?ErreurMetier $refus)
    {
        $this->identite = $identite;
        $this->refus    = $refus;
    }

    public static function acteur(int $id, string $nom = 'test'): self
    {
        return new self(new Identite($id, $nom), null);
    }

    /** @param string $type une constante de ErreurMetier. */
    public static function refuse(string $type = ErreurMetier::JETON_ABSENT): self
    {
        return new self(null, new ErreurMetier($type, 'refus simule'));
    }

    public function identifier(): Identite
    {
        $this->appels++;
        if ($this->refus !== null) {
            throw $this->refus;
        }
        return $this->identite;
    }
}

