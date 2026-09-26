<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Connexion PDO unique, ouverte au dernier moment.
 *
 * PARESSEUSE PAR CONSTRUCTION. Tant que personne n'appelle pdo(), aucune
 * socket n'est ouverte. La racine de composition peut donc cabler toute
 * l'application -- depots, journal, authentification -- sans toucher la base,
 * et un endpoint qui refuse une requete malformee repond sans avoir jamais
 * consulte le serveur.
 *
 * @package PhpCleanCode\Infrastructure
 */
final class FabriqueConnexion
{
    private string $dsn;
    private string $utilisateur;
    private string $motDePasse;
    private array $options;
    private ?string $base;
    private ?PDO $pdo = null;

    /**
     * @param string|null $base si fournie, un "USE `base`" explicite est
     *        emis juste apres la connexion. FILET DE SECURITE : des qu'une
     *        seule requete du projet nomme une table sans prefixer sa base,
     *        c'est la base par defaut du compte MySQL qui decide -- et une
     *        copie de developpement se met a lire et ecrire en production
     *        sans qu'aucun test ne le signale.
     * @param array<int,mixed> $options options PDO supplementaires.
     */
    public function __construct(
        string $dsn,
        string $utilisateur,
        string $motDePasse,
        ?string $base = null,
        array $options = []
    ) {
        $this->dsn         = $dsn;
        $this->utilisateur = $utilisateur;
        $this->motDePasse  = $motDePasse;
        $this->base        = $base;
        $this->options     = $options + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    /** @throws RuntimeException si le serveur est injoignable. */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            try {
                $this->pdo = new PDO($this->dsn, $this->utilisateur, $this->motDePasse, $this->options);
            } catch (PDOException $e) {
                // Le message de PDO contient l'hote, le port et parfois
                // l'utilisateur. Il ne doit jamais remonter au consommateur.
                throw new RuntimeException('connexion a la base impossible', 0, $e);
            }
            if ($this->base !== null) {
                $this->pdo->exec('USE `' . $this->base . '`');
            }
        }
        return $this->pdo;
    }

    /** Vrai si la connexion a deja ete ouverte. Utile aux tests. */
    public function estOuverte(): bool
    {
        return $this->pdo !== null;
    }
}

