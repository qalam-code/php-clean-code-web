<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Validation;

use InvalidArgumentException;

/** Validation de tableaux d'entree avec regles declaratives. */
final class ValidateurDonnees
{
    /**
     * Regles : required, string, trim, min, max, email et callable.
     * Les longueurs sont mesurees en caracteres UTF-8.
     * @param array<string,mixed> $entree
     * @param array<string,array<string,mixed>> $regles
     * @param array<string,string> $messages personnalisables par champ
     */
    public function valider(array $entree, array $regles, array $messages = []): ResultatValidation
    {
        $donneesValides = [];
        $erreurs = [];
        foreach ($regles as $champ => $reglesChamp) {
            if (!is_string($champ) || !is_array($reglesChamp)) {
                throw new InvalidArgumentException('Chaque champ doit avoir un nom et une liste de regles.');
            }
            $valeur = array_key_exists($champ, $entree) ? $entree[$champ] : null;
            if (($reglesChamp['trim'] ?? false) === true && is_string($valeur)) {
                $valeur = trim($valeur);
            }
            $donneesValides[$champ] = $valeur;

            $message = null;
            $present = $valeur !== null && $valeur !== '';
            if (($reglesChamp['required'] ?? false) === true && !$present) {
                $message = 'Ce champ est obligatoire.';
            } elseif (!$present) {
                continue;
            } elseif (($reglesChamp['string'] ?? false) === true && !is_string($valeur)) {
                $message = 'Ce champ doit etre du texte.';
            } elseif (is_string($valeur)) {
                $longueur = preg_match_all('/./us', $valeur, $caracteres);
                if ($longueur === false) {
                    $message = 'Ce texte contient un encodage invalide.';
                } elseif (isset($reglesChamp['min']) && $longueur < $this->limite($reglesChamp['min'], $champ)) {
                    $message = 'Ce champ doit contenir au moins ' . $reglesChamp['min'] . ' caracteres.';
                } elseif (isset($reglesChamp['max']) && $longueur > $this->limite($reglesChamp['max'], $champ)) {
                    $message = 'Ce champ ne doit pas depasser ' . $reglesChamp['max'] . ' caracteres.';
                } elseif (($reglesChamp['email'] ?? false) === true && filter_var($valeur, FILTER_VALIDATE_EMAIL) === false) {
                    $message = 'Cette adresse e-mail est invalide.';
                }
            }

            if ($message === null && isset($reglesChamp['callback'])) {
                if (!is_callable($reglesChamp['callback'])) {
                    throw new InvalidArgumentException('La regle callback de ' . $champ . ' doit etre appelable.');
                }
                $resultat = call_user_func($reglesChamp['callback'], $valeur, $donneesValides);
                if ($resultat !== true) {
                    $message = is_string($resultat) ? $resultat : 'Ce champ est invalide.';
                }
            }
            if ($message !== null) {
                $erreurs[$champ] = isset($messages[$champ]) ? $messages[$champ] : $message;
            }
        }
        return new ResultatValidation($donneesValides, $erreurs);
    }

    private function limite($valeur, string $champ): int
    {
        if (!is_int($valeur) || $valeur < 0) {
            throw new InvalidArgumentException('La limite de ' . $champ . ' doit etre un entier positif ou nul.');
        }
        return $valeur;
    }
}
