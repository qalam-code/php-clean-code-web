<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;

/** Ajoute les en-têtes CORS aux réponses après vérification d'une politique explicite. */
final class CorsMiddleware
{
    private $origines;
    private $methodes;
    private $entetesAutorises;
    private $entetesExposees;
    private $identifiants;
    private $ageMax;

    /**
     * @param array<int,string> $origines origines exactes autorisées, ou ['*'] sans identifiants
     * @param array<int,string> $methodes méthodes permises pour le partage CORS
     * @param array<int,string> $entetesAutorises en-têtes de requête autorisés pour les précontrôles
     * @param bool $identifiants autorise l'envoi de cookies ou d'autres identifiants
     * @param int $ageMax durée de cache du résultat de précontrôle, en secondes
     * @param array<int,string> $entetesExposees en-têtes de réponse lisibles par le navigateur
     */
    public function __construct(
        array $origines,
        array $methodes,
        array $entetesAutorises = [],
        bool $identifiants = false,
        int $ageMax = 0,
        array $entetesExposees = []
    ) {
        if ($origines === [] || $methodes === [] || $ageMax < 0) {
            throw new InvalidArgumentException('La politique CORS doit contenir des origines et methodes et un age non negatif.');
        }
        foreach ($origines as $origine) {
            if (!is_string($origine) || $origine === '' || preg_match('/[\r\n,]/', $origine)) {
                throw new InvalidArgumentException('Chaque origine CORS doit etre une valeur textuelle sans virgule ni saut de ligne.');
            }
        }
        if (in_array('*', $origines, true) && (count($origines) !== 1 || $identifiants)) {
            throw new InvalidArgumentException('L origine * doit etre seule et ne peut pas etre combinee aux identifiants.');
        }

        $this->origines = array_values(array_unique($origines));
        $this->methodes = $this->normaliserMethodes($methodes);
        $this->entetesAutorises = $this->normaliserEntetes($entetesAutorises);
        $this->entetesExposees = $this->normaliserEntetes($entetesExposees);
        $this->identifiants = $identifiants;
        $this->ageMax = $ageMax;
    }

    /** Le middleware enveloppe le routeur pour traiter aussi ses réponses OPTIONS 204. */
    public function traiter(Requete $requete, callable $suite): ReponseWeb
    {
        $reponse = $suite();
        if (!$reponse instanceof ReponseWeb) {
            throw new InvalidArgumentException('La suite CORS doit retourner une ReponseWeb.');
        }

        $origine = $requete->entete('Origin');
        if (!is_string($origine) || $origine === '') {
            return $reponse;
        }

        $precontrole = $requete->methode() === 'OPTIONS'
            && is_string($requete->entete('Access-Control-Request-Method'))
            && trim($requete->entete('Access-Control-Request-Method')) !== '';
        $vary = $precontrole
            ? ['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers']
            : ['Origin'];

        if (!$this->origineAutorisee($origine)) {
            return $this->avecEntetes($reponse, [], $vary);
        }

        if ($precontrole) {
            return $this->reponsePrecontrole($requete, $reponse, $origine, $vary);
        }

        if (!in_array($requete->methode(), $this->methodes, true)) {
            return $this->avecEntetes($reponse, [], $vary);
        }

        $entetes = $this->entetesCommuns($origine);
        if ($this->entetesExposees !== []) {
            $entetes['Access-Control-Expose-Headers'] = implode(', ', $this->entetesExposees);
        }
        return $this->avecEntetes($reponse, $entetes, $vary);
    }

    private function reponsePrecontrole(Requete $requete, ReponseWeb $reponse, string $origine, array $vary): ReponseWeb
    {
        $allow = $reponse->entetes()['Allow'] ?? null;
        if ($reponse->code() !== 204 || !is_string($allow)) {
            return $this->avecEntetes($reponse, [], $vary);
        }

        $methodesRoute = array_map('strtoupper', array_map('trim', explode(',', $allow)));
        $methodesPermises = array_values(array_intersect($this->methodes, $methodesRoute));
        $methodeDemandee = strtoupper(trim($requete->entete('Access-Control-Request-Method')));
        if (!in_array($methodeDemandee, $methodesPermises, true)) {
            return $this->avecEntetes($reponse, [], $vary);
        }

        $entetesDemandees = $this->lireEntetesDemandees($requete->entete('Access-Control-Request-Headers'));
        if ($entetesDemandees === null) {
            return $this->avecEntetes($reponse, [], $vary);
        }
        foreach ($entetesDemandees as $entete) {
            if (!in_array(strtolower($entete), array_map('strtolower', $this->entetesAutorises), true)) {
                return $this->avecEntetes($reponse, [], $vary);
            }
        }

        $entetes = $this->entetesCommuns($origine);
        $entetes['Access-Control-Allow-Methods'] = implode(', ', $methodesPermises);
        if ($entetesDemandees !== []) {
            $entetes['Access-Control-Allow-Headers'] = implode(', ', $entetesDemandees);
        }
        if ($this->ageMax > 0) {
            $entetes['Access-Control-Max-Age'] = (string) $this->ageMax;
        }
        return $this->avecEntetes($reponse, $entetes, $vary);
    }

    private function entetesCommuns(string $origine): array
    {
        $entetes = [
            'Access-Control-Allow-Origin' => $this->origines === ['*'] ? '*' : $origine,
        ];
        if ($this->identifiants) {
            $entetes['Access-Control-Allow-Credentials'] = 'true';
        }
        return $entetes;
    }

    private function origineAutorisee(string $origine): bool
    {
        return $this->origines === ['*'] || in_array($origine, $this->origines, true);
    }

    /**
     * @return array<int,string>|null null indique une liste invalide ou non autorisée
     */
    /** @return array<int,string>|null */
    private function lireEntetesDemandees($valeur)
    {
        if ($valeur === null) {
            return [];
        }
        if (!is_string($valeur)) {
            return null;
        }
        if (trim($valeur) === '') {
            return [];
        }

        $entetes = [];
        foreach (explode(',', $valeur) as $entete) {
            $entete = trim($entete);
            if (!preg_match('/\A[A-Za-z][A-Za-z0-9-]*\z/', $entete)) {
                return null;
            }
            $entetes[] = $entete;
        }
        return $entetes;
    }

    private function normaliserMethodes(array $methodes): array
    {
        $normalisees = [];
        foreach ($methodes as $methode) {
            if (!is_string($methode) || !preg_match('/\A[A-Za-z]+\z/', $methode)) {
                throw new InvalidArgumentException('Chaque methode CORS doit etre un nom HTTP textuel.');
            }
            $normalisees[] = strtoupper($methode);
        }
        return array_values(array_unique($normalisees));
    }

    private function normaliserEntetes(array $entetes): array
    {
        $normalisees = [];
        foreach ($entetes as $entete) {
            if (!is_string($entete) || !preg_match('/\A[A-Za-z][A-Za-z0-9-]*\z/', $entete)) {
                throw new InvalidArgumentException('Chaque nom d en-tete CORS doit etre valide.');
            }
            $normalisees[] = $entete;
        }
        return array_values(array_unique($normalisees));
    }

    /** Fusionne Vary et remplace les en-têtes CORS sans supprimer ceux de la réponse. */
    private function avecEntetes(ReponseWeb $reponse, array $ajouts, array $valeursVary): ReponseWeb
    {
        $entetes = $reponse->entetes();
        $varyExistants = [];
        foreach ($entetes as $nom => $valeur) {
            if (strtolower($nom) === 'vary') {
                $varyExistants = array_merge($varyExistants, array_map('trim', explode(',', $valeur)));
                unset($entetes[$nom]);
            }
            foreach ($ajouts as $nomAjout => $valeurAjout) {
                if (strtolower($nom) === strtolower($nomAjout)) {
                    unset($entetes[$nom]);
                }
            }
        }
        foreach ($ajouts as $nom => $valeur) {
            $entetes[$nom] = $valeur;
        }

        if (!in_array('*', $varyExistants, true)) {
            foreach ($valeursVary as $valeur) {
                $dejaPresent = false;
                foreach ($varyExistants as $existant) {
                    if (strcasecmp($existant, $valeur) === 0) {
                        $dejaPresent = true;
                        break;
                    }
                }
                if (!$dejaPresent) {
                    $varyExistants[] = $valeur;
                }
            }
            $entetes['Vary'] = implode(', ', array_filter($varyExistants, 'strlen'));
        } else {
            $entetes['Vary'] = '*';
        }

        return $reponse->avecEntetes($entetes);
    }
}
