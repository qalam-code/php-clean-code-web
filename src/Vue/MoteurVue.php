<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Vue;

use InvalidArgumentException;
use RuntimeException;

/**
 * Rend les composants de vues et, au besoin, leur layout.
 * Les vues HTML recoivent des marqueurs echappes automatiquement ; les vues
 * PHP sont des templates de confiance et doivent echapper leurs sorties.
 */
final class MoteurVue
{
    private $repertoire;
    private $baseActifs;

    public function __construct(string $repertoire, string $baseActifs = '/assets/vues')
    {
        // Conserver le chemin reel comme racine de confiance pour verifier
        // ensuite que les fichiers resolus restent bien dans ce repertoire.
        $repertoireReel = realpath($repertoire);
        if ($repertoireReel === false || !is_dir($repertoireReel)) {
            throw new RuntimeException('Le repertoire des vues est introuvable.');
        }
        if ($baseActifs === '' || $baseActifs[0] !== '/' || strpos($baseActifs, '//') === 0 || strpos($baseActifs, '..') !== false) {
            throw new InvalidArgumentException('La base des actifs doit etre un chemin local absolu valide.');
        }
        $this->repertoire = rtrim($repertoireReel, DIRECTORY_SEPARATOR);
        // Les actifs sont servis par l'application sous une URL locale ; ils
        // ne sont pas copies dans le repertoire public.
        $this->baseActifs = '/' . trim($baseActifs, '/');
    }

    /** Rend une vue seule ou une vue partielle PHP. */
    public function rendre(string $nomVue, array $donnees = []): string
    {
        return $this->rendreFichier($nomVue, $donnees);
    }

    /** Rend la vue dans un layout et associe les actifs du composant. */
    public function rendreAvecLayout(string $nomVue, string $layout, array $donnees = []): string
    {
        $contenu = $this->rendreFichier($nomVue, $donnees);
        $donneesLayout = $donnees;
        // Le contenu est deja un fragment HTML rendu ; le layout l'insere
        // comme balisage, tandis que ses autres valeurs doivent etre echappees.
        $donneesLayout['contenu'] = $contenu;
        $donneesLayout['classeVue'] = 'vue-' . str_replace('/', '-', $nomVue);
        // Le layout utilise ces listes pour generer les balises link et script.
        $donneesLayout['actifsCss'] = [$this->baseActifs . '/' . $nomVue . '/style.css'];
        $donneesLayout['actifsJs'] = [$this->baseActifs . '/' . $nomVue . '/script.js'];
        return $this->rendreFichier($layout, $donneesLayout);
    }

    /** @param array<string,mixed> $donnees */
    private function rendreFichier(string $nomVue, array $donnees): string
    {
        // Un nom relatif restreint empeche les traversals comme ../secret.php.
        if (!preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', $nomVue)) {
            throw new RuntimeException('Nom de vue invalide.');
        }
        $base = $this->repertoire . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $nomVue);
        $baseComposant = $base . DIRECTORY_SEPARATOR . 'vue';
        // Convention d'un composant : vue.html ou vue.php dans son dossier.
        $cheminHtml = realpath($baseComposant . '.html');
        $cheminPhp = realpath($baseComposant . '.php');
        if ($cheminHtml === false && $cheminPhp === false) {
            // Compatibilite avec les layouts et partiels PHP places directement
            // sous resources/vues (ex. layouts/principal.php).
            $cheminHtml = realpath($base . '.html');
            $cheminPhp = realpath($base . '.php');
        }
        if ($cheminHtml !== false && $cheminPhp !== false) {
            throw new RuntimeException('La vue ne peut pas avoir simultanement une version HTML et PHP : ' . $nomVue);
        }
        $chemin = $cheminHtml !== false ? $cheminHtml : $cheminPhp;
        // realpath neutralise les liens et segments ; le prefixe confirme que
        // le fichier final reste sous la racine de vues autorisee.
        if ($chemin === false || strpos($chemin, $this->repertoire . DIRECTORY_SEPARATOR) !== 0 || !is_file($chemin)) {
            throw new RuntimeException('Vue introuvable : ' . $nomVue);
        }

        if ($cheminHtml !== false) {
            $contenu = file_get_contents($chemin);
            if ($contenu === false) {
                throw new RuntimeException('Impossible de lire la vue : ' . $nomVue);
            }
            // Les fichiers HTML simples n'executent pas de PHP : seuls les
            // marqueurs nommes sont remplaces, et les valeurs sont echappees.
            $rendu = preg_replace_callback('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', function (array $correspondance) use ($donnees) {
                $cle = $correspondance[1];
                if (!array_key_exists($cle, $donnees)) {
                    throw new RuntimeException('Donnee de vue absente : ' . $cle);
                }
                $valeur = $donnees[$cle];
                if (!is_scalar($valeur) && $valeur !== null) {
                    throw new RuntimeException('Une variable de vue HTML doit etre scalaire : ' . $cle);
                }
                return $this->echapper($valeur);
            }, $contenu);
            if ($rendu === null) {
                throw new RuntimeException('Impossible de traiter la vue : ' . $nomVue);
            }
            return $rendu;
        }

        // Les templates PHP doivent etre du code de confiance. Le tampon
        // capture leur sortie pour retourner une chaine sans faire d'echo.
        $niveauTampon = ob_get_level();
        ob_start();
        try {
            // EXTR_SKIP evite qu'une donnee de vue remplace les variables locales.
            extract($donnees, EXTR_SKIP);
            require $chemin;
            return (string) ob_get_clean();
        } catch (\Throwable $erreur) {
            // Retirer uniquement les tampons ouverts par ce rendu, puis laisser
            // l'aiguillage convertir l'exception en reponse d'erreur.
            while (ob_get_level() > $niveauTampon) {
                ob_end_clean();
            }
            throw $erreur;
        }
    }

    public function echapper($valeur): string
    {
        return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
