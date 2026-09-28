<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Presentation;

/** Reponse HTTP destinee au rendu HTML. */
final class ReponseHtml
{
    private $code;
    private $corps;
    private $entetes;

    /** @param array<string,string> $entetes */
    public function __construct(int $code, string $corps, array $entetes = [])
    {
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

    public function envoyer()
    {
        if (!headers_sent()) {
            http_response_code($this->code);
            header('Content-Type: text/html; charset=utf-8');
            foreach ($this->entetes as $nom => $valeur) {
                header($nom . ': ' . $valeur);
            }
        }
        echo $this->corps;
    }
}