<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;
use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use RuntimeException;

/**
 * Sert les feuilles CSS et scripts des composants, depuis leur dossier de vue.
 * Les fichiers restent dans resources/vues : le framework les expose par cette route.
 */
final class ServeurActifsVue
{
    private $repertoireVues;
    private $baseUrl;

    public function __construct(string $repertoireVues, string $baseUrl = '/assets/vues')
    {
        // Mémorise un chemin absolu existant pour résoudre ensuite les actifs depuis une racine connue.
        $repertoireReel = realpath($repertoireVues);
        if ($repertoireReel === false || !is_dir($repertoireReel)) {
            throw new RuntimeException('Le repertoire des composants de vues est introuvable.');
        }
        // La base doit être un chemin URL local, sans hôte ni segment de remontée.
        if ($baseUrl === '' || $baseUrl[0] !== '/' || strpos($baseUrl, '//') === 0 || strpos($baseUrl, '..') !== false) {
            throw new InvalidArgumentException('La base URL des actifs doit etre un chemin local valide.');
        }
        $this->repertoireVues = rtrim($repertoireReel, DIRECTORY_SEPARATOR);
        $this->baseUrl = '/' . trim($baseUrl, '/');
    }

    /** @return ReponseWeb|null null si le chemin n'est pas un actif de vue. */
    public function servir(Requete $requete)
    {
        $prefixe = $this->baseUrl . '/';
        // Ce serveur ne prend en charge que son espace d'URL ; les autres requêtes continuent leur routage normal.
        if (strpos($requete->chemin(), $prefixe) !== 0) {
            return null;
        }
        // Les actifs sont lus sans effet de bord : seuls GET et HEAD sont autorisés.
        if (!in_array($requete->methode(), ['GET', 'HEAD'], true)) {
            return new ReponseWeb(405, '', [
                'Allow' => 'GET, HEAD',
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $relatif = substr($requete->chemin(), strlen($prefixe));
        // N'accepte que les chemins de composants et les deux noms de fichiers prévus.
        // Cette forme interdit notamment les points, les encodages inattendus et la traversée de répertoires.
        if (!preg_match('/\A([A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*)\/(style\.css|script\.js)\z/', $relatif, $correspondance)) {
            return $this->introuvable();
        }
        $composant = str_replace('/', DIRECTORY_SEPARATOR, $correspondance[1]);
        $nomFichier = $correspondance[2];
        // realpath résout aussi les liens symboliques ; le contrôle qui suit garantit que la cible reste sous la racine.
        $chemin = realpath($this->repertoireVues . DIRECTORY_SEPARATOR . $composant . DIRECTORY_SEPARATOR . $nomFichier);
        if ($chemin === false
            || strpos($chemin, $this->repertoireVues . DIRECTORY_SEPARATOR) !== 0
            || !is_file($chemin)
        ) {
            return $this->introuvable();
        }

        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            throw new RuntimeException('Impossible de lire un actif de vue.');
        }
        $type = $nomFichier === 'style.css'
            ? 'text/css; charset=utf-8'
            : 'text/javascript; charset=utf-8';
        // L'ETag permet au navigateur de revalider le contenu sans retransférer le fichier s'il n'a pas changé.
        $etag = '"' . sha1($contenu) . '"';
        $entetes = [
            'Content-Type' => $type,
            'Cache-Control' => 'no-cache',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($requete->entete('If-None-Match') === $etag) {
            return new ReponseWeb(304, '', $entetes);
        }
        // HEAD renvoie les mêmes métadonnées qu'un GET, mais aucun corps.
        return new ReponseWeb(200, $requete->methode() === 'HEAD' ? '' : $contenu, $entetes);
    }

    private function introuvable(): ReponseWeb
    {
        return new ReponseWeb(404, 'Actif introuvable.', [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
