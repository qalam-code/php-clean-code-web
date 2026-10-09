<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;

/** Ajoute des protections HTTP de base sans imposer une politique propre à l'application. */
final class EnTetesSecuriteMiddleware
{
    private $entetes;

    /** @param array<string,string|null> $configuration Valeurs à remplacer ou null pour désactiver */
    public function __construct(array $configuration = [])
    {
        $entetes = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        foreach ($configuration as $nom => $valeur) {
            if (!is_string($nom) || !preg_match('/\A[A-Za-z0-9-]+\z/', $nom)) {
                throw new InvalidArgumentException('Un nom d en-tete de securite est invalide.');
            }
            if ($valeur !== null && (!is_string($valeur) || preg_match('/[\r\n]/', $valeur))) {
                throw new InvalidArgumentException('Une valeur d en-tete de securite doit etre une chaine sans saut de ligne ou null.');
            }
            foreach ($entetes as $nomExistant => $valeurExistante) {
                if (strcasecmp($nom, $nomExistant) === 0) {
                    unset($entetes[$nomExistant]);
                }
            }
            if ($valeur !== null) {
                $entetes[$nom] = $valeur;
            }
        }
        $this->entetes = $entetes;
    }

    /** Enveloppe toute réponse HTTP, y compris les erreurs et les actifs statiques. */
    public function traiter(Requete $requete, callable $suite): ReponseWeb
    {
        $reponse = $suite();
        if (!$reponse instanceof ReponseWeb) {
            throw new InvalidArgumentException('La suite de securite doit retourner une ReponseWeb.');
        }
        $entetes = $reponse->entetes();
        foreach ($this->entetes as $nom => $valeur) {
            foreach ($entetes as $nomExistant => $valeurExistante) {
                if (strcasecmp($nom, $nomExistant) === 0) {
                    unset($entetes[$nomExistant]);
                }
            }
            $entetes[$nom] = $valeur;
        }
        return $reponse->avecEntetes($entetes);
    }
}