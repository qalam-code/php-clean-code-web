<?php
declare(strict_types=1);

namespace PhpCleanCode\Test;

use PhpCleanCode\Application\Port\JetonInterface;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * Jetons sans cryptographie : la charge est encodee en JSON, telle quelle.
 *
 * A N'UTILISER QUE DANS LES TESTS QUI NE PORTENT PAS SUR LA SECURITE. Il
 * accepte tout ce qu'il a emis et refuse le reste, ce qui suffit a eprouver
 * un cas d'usage -- mais ne dit rien de la solidite de la vraie signature.
 * Les tests de JetonJwt (signature falsifiee, "alg":"none", expiration)
 * doivent viser l'implementation reelle.
 *
 * @package PhpCleanCode\Test
 */
final class JetonFactice implements JetonInterface
{
    /** Mettre a un type d'ErreurMetier pour faire echouer toute verification. */
    public $refuseAvec = null;

    public function emettre(array $charge, int $dureeSecondes): string
    {
        $charge['exp'] = time() + $dureeSecondes;
        return base64_encode((string) json_encode($charge));
    }

    public function verifier(string $jeton): array
    {
        if ($this->refuseAvec !== null) {
            throw new ErreurMetier($this->refuseAvec, 'refus simule');
        }
        $brut = base64_decode($jeton, true);
        if ($brut === false) {
            throw ErreurMetier::jetonMalforme();
        }
        $charge = json_decode($brut, true);
        if (!is_array($charge)) {
            throw ErreurMetier::jetonMalforme();
        }
        // Meme regle que l'implementation reelle : sans exp, on refuse.
        if (!isset($charge['exp'])) {
            throw ErreurMetier::jetonMalforme();
        }
        if (time() >= (int) $charge['exp']) {
            throw ErreurMetier::jetonExpire();
        }
        return $charge;
    }
}

