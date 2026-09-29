<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Validation;

/** Resultat stable d'une validation de formulaire. */
final class ResultatValidation
{
    private $donnees;
    private $erreurs;

    public function __construct(array $donnees, array $erreurs)
    {
        $this->donnees = $donnees;
        $this->erreurs = $erreurs;
    }

    public function estValide(): bool
    {
        return $this->erreurs === [];
    }

    public function donnees(): array
    {
        return $this->donnees;
    }

    /** @return array<string,string> Une premiere erreur par champ. */
    public function erreurs(): array
    {
        return $this->erreurs;
    }

    public function premiereErreur()
    {
        foreach ($this->erreurs as $erreur) {
            return $erreur;
        }
        return null;
    }
}
