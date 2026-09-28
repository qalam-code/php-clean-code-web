<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Vue;

use RuntimeException;

/** Charge des vues PHP depuis un repertoire fixe. */
final class MoteurVue
{
    private $repertoire;

    public function __construct(string $repertoire)
    {
        $repertoireReel = realpath($repertoire);
        if ($repertoireReel === false || !is_dir($repertoireReel)) {
            throw new RuntimeException('Le repertoire des vues est introuvable.');
        }
        $this->repertoire = rtrim($repertoireReel, DIRECTORY_SEPARATOR);
    }

    /** Rend une vue seule ou une vue partielle. */
    public function rendre(string $nomVue, array $donnees = []): string
    {
        return $this->rendreFichier($nomVue, $donnees);
    }

    /** Rend le contenu d'une vue dans un layout qui recoit la variable $contenu. */
    public function rendreAvecLayout(string $nomVue, string $layout, array $donnees = []): string
    {
        $contenu = $this->rendreFichier($nomVue, $donnees);
        $donneesLayout = $donnees;
        $donneesLayout['contenu'] = $contenu;
        return $this->rendreFichier($layout, $donneesLayout);
    }

    /** @param array<string,mixed> $donnees */
    private function rendreFichier(string $nomVue, array $donnees): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', $nomVue)) {
            throw new RuntimeException('Nom de vue invalide.');
        }
        $chemin = realpath($this->repertoire . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $nomVue) . '.php');
        if ($chemin === false || strpos($chemin, $this->repertoire . DIRECTORY_SEPARATOR) !== 0 || !is_file($chemin)) {
            throw new RuntimeException('Vue introuvable : ' . $nomVue);
        }

        $niveauTampon = ob_get_level();
        ob_start();
        try {
            extract($donnees, EXTR_SKIP);
            require $chemin;
            return (string) ob_get_clean();
        } catch (\Throwable $erreur) {
            while (ob_get_level() > $niveauTampon) {
                ob_end_clean();
            }
            throw $erreur;
        }
    }

    /** Echappement a utiliser pour toute valeur non fiable affichee en HTML. */
    public function echapper($valeur): string
    {
        return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}