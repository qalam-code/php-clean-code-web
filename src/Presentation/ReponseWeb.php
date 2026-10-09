<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Presentation;

/**
 * Reponse HTTP generique, utilisable pour HTML et actifs statiques.
 * Elle conserve les donnees de reponse ; envoyer() les transmet ensuite a PHP.
 */
class ReponseWeb
{
    private $code;
    private $corps;
    private $entetes;

    /** @param array<string,string> $entetes */
    public function __construct(int $code, string $corps, array $entetes = [])
    {
        // Garder statut, corps et en-tetes ensemble permet aux routes de retourner une reponse complete.
        $this->code = $code;
        $this->corps = $corps;
        $this->entetes = $entetes;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function corps(): string
    {
        return $this->corps;
    }

    /** @return array<string,string> */
    public function entetes(): array
    {
        return $this->entetes;
    }

    /**
     * Retourne une copie avec de nouveaux en-têtes sans perdre le type concret de la réponse.
     * Le clonage permet aussi aux réponses spécialisées de conserver leur sémantique.
     *
     * @param array<string,string> $entetes
     */
    public function avecEntetes(array $entetes): ReponseWeb
    {
        $copie = clone $this;
        $copie->entetes = $entetes;
        return $copie;
    }
    public function envoyer()
    {
        // Les en-tetes et le statut ne peuvent plus etre modifies si PHP a deja commence la sortie.
        if (!headers_sent()) {
            http_response_code($this->code);
            foreach ($this->entetes as $nom => $valeur) {
                header($nom . ': ' . $valeur);
            }
        }
        // Le corps est quand meme emis ; cette classe ne gere pas le cycle de vie du processus HTTP.
        echo $this->corps;
    }
}
