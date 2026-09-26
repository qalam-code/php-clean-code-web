<?php
declare(strict_types=1);

namespace PhpCleanCode\Presentation;

/**
 * Une reponse : un code, un corps, des en-tetes. Rien d'autre.
 *
 * Objet de transport, sans logique. Son interet est de rendre la reponse
 * INSPECTABLE : un test lit code() et corps() directement, sans capturer une
 * sortie ni analyser du JSON. C'est ce qui permet de verifier un contrat
 * d'API -- les codes HTTP et les cles rendues -- sans serveur web.
 *
 * L'ORDRE DES CLES DU TABLEAU EST L'ORDRE DU JSON. Quand des tiers
 * consomment deja l'API, il fait partie du contrat au meme titre que les
 * noms de cles : ne le changez pas en reorganisant du code.
 *
 * @package PhpCleanCode\Presentation
 */
final class ReponseHttp
{
    private $code;
    private $corps;
    private $entetes;

    /**
     * @param array<string,mixed> $corps
     * @param array<string,string> $entetes
     */
    public function __construct(int $code, array $corps, array $entetes = [])
    {
        $this->code    = $code;
        $this->corps   = $corps;
        $this->entetes = $entetes;
    }

    public function code(): int
    {
        return $this->code;
    }

    /** @return array<string,mixed> */
    public function corps(): array
    {
        return $this->corps;
    }

    /** @return array<string,string> */
    public function entetes(): array
    {
        return $this->entetes;
    }

    public function json(): string
    {
        // JSON_UNESCAPED_UNICODE : sans lui, "reglee" accentue part en
        // sequences \uXXXX, illisibles dans les journaux comme en debogage.
        $options = JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PARTIAL_OUTPUT_ON_ERROR;
        // Disponible a partir de PHP 7.2. Sous PHP 7.0, le mode partiel
        // conserve un JSON valide en remplaçant une valeur fautive par null.
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $options |= constant('JSON_INVALID_UTF8_SUBSTITUTE');
        }
        return (string) json_encode($this->corps, $options);
    }

    /**
     * SEUL ENDROIT DU CODE QUI ECRIT SUR LA SORTIE. Tout le reste retourne
     * des objets ; un cas d'usage ou un depot qui fait echo est un cas
     * d'usage qu'on ne pourra plus tester.
     */
    public function envoyer(){
        if (!headers_sent()) {
            http_response_code($this->code);
            header('Content-Type: application/json; charset=utf-8');
            foreach ($this->entetes as $nom => $valeur) {
                header($nom . ': ' . $valeur);
            }
        }
        echo $this->json();
    }
}

