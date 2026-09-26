<?php
declare(strict_types=1);

namespace PhpCleanCode\Http;

/**
 * La requete entrante, lue une fois pour toutes.
 *
 * Les superglobales sont lues dans depuisGlobales() et nulle part ailleurs.
 * C'est ce qui rend tout le reste testable : un test construit une Requete
 * avec les valeurs qu'il veut, sans toucher a $_POST ni simuler php://input.
 *
 * Les donnees sont volontairement rendues BRUTES, sans echappement. Echapper
 * a l'entree corrompt ce qu'on enregistre et ne protege de rien : c'est a la
 * sortie -- requete preparee pour SQL, json_encode pour la reponse -- que
 * l'echappement a un sens, parce que la destination est alors connue.
 *
 * @package PhpCleanCode\Http
 */
final class Requete
{
    private string $methode;
    private string $chemin;
    private array $corps;
    private array $requeteUrl;
    private array $entetes;

    public function __construct(
        string $methode,
        string $chemin,
        array $corps = [],
        array $requeteUrl = [],
        array $entetes = []
    ) {
        $this->methode    = strtoupper($methode);
        $this->chemin     = $chemin;
        $this->corps      = $corps;
        $this->requeteUrl = $requeteUrl;
        $this->entetes    = array_change_key_case($entetes, CASE_LOWER);
    }

    public static function depuisGlobales(): self
    {
        $brut  = (string) file_get_contents('php://input');
        $corps = [];
        if ($brut !== '') {
            $decode = json_decode($brut, true);
            $corps  = is_array($decode) ? $decode : [];
        }
        // Un formulaire classique n'arrive pas par php://input.
        if ($corps === [] && $_POST !== []) {
            $corps = $_POST;
        }

        $chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $chemin,
            $corps,
            $_GET,
            self::entetesDuServeur()
        );
    }

    public function methode(): string
    {
        return $this->methode;
    }

    public function chemin(): string
    {
        return $this->chemin;
    }

    /** @return array<string,mixed> */
    public function corps(): array
    {
        return $this->corps;
    }

    /** @return mixed valeur du corps, puis de la query string, sinon $defaut. */
    public function parametre(string $cle, $defaut = null)
    {
        if (array_key_exists($cle, $this->corps)) {
            return $this->corps[$cle];
        }
        return $this->requeteUrl[$cle] ?? $defaut;
    }

    /**
     * Les cles absentes ET les chaines vides comptent comme manquantes : une
     * valeur vide passee par erreur n'est pas une valeur.
     *
     * @param array<int,string> $cles
     * @return array<int,string> celles qui manquent, dans l'ordre demande.
     */
    public function manquants(array $cles): array
    {
        $absents = [];
        foreach ($cles as $cle) {
            $valeur = $this->parametre($cle);
            if ($valeur === null || $valeur === '') {
                $absents[] = $cle;
            }
        }
        return $absents;
    }

    public function entete(string $nom): ?string
    {
        return $this->entetes[strtolower($nom)] ?? null;
    }

    /** @return array<string,string> */
    private static function entetesDuServeur(): array
    {
        // getallheaders() manque sous certaines configurations (php-fpm avec
        // nginx, CLI), et c'est precisement la qu'Authorization se perd.
        if (function_exists('getallheaders')) {
            $entetes = getallheaders();
            if (is_array($entetes) && $entetes !== []) {
                return $entetes;
            }
        }
        $entetes = [];
        foreach ($_SERVER as $cle => $valeur) {
            if (strpos((string) $cle, 'HTTP_') === 0) {
                $nom = str_replace('_', '-', substr((string) $cle, 5));
                $entetes[$nom] = (string) $valeur;
            }
        }
        // Apache masque Authorization quand CGIPassAuth est desactive ; la
        // reecriture de .htaccess le remet ici.
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $entetes['Authorization'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        return $entetes;
    }
}

