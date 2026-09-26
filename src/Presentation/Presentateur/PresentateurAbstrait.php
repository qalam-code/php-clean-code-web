<?php
declare(strict_types=1);

namespace PhpCleanCode\Presentation\Presentateur;

use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Traduit un resultat de domaine en reponse HTTP.
 *
 * C'EST ICI, ET NULLE PART AILLEURS, QUE VIT LE CONTRAT DE L'API. Les codes
 * HTTP, les noms de cles, les libelles : un presentateur par endpoint, et le
 * contrat se lit d'un coup d'oeil au lieu d'etre disperse dans les depots et
 * les cas d'usage.
 *
 * La consequence pratique compte autant que le principe : un presentateur
 * n'a aucune dependance -- ni base, ni reseau, ni cas d'usage. Un test
 * l'instancie et verifie le contrat entier en memoire, sans serveur.
 *
 * REGLE DE SURETE : un presentateur ne laisse jamais passer le message d'une
 * exception technique. "SQLSTATE[HY000] [2002] Connection refused" livre au
 * consommateur le moteur, l'hote et parfois l'utilisateur de la base.
 * Traduisez, ne relayez pas.
 *
 * @package PhpCleanCode\Presentation\Presentateur
 */
abstract class PresentateurAbstrait
{
    /**
     * Le type de l'erreur decide ; le message de l'exception ne sert qu'au
     * journal. Terminez par un appel a parent::traduire() pour que les types
     * de la bibliotheque restent couverts.
     */
    abstract public function traduire(ErreurMetier $erreur): ReponseHttp;

    /**
     * Réponse pour une méthode HTTP refusée. Les applications peuvent
     * redéfinir le corps pour respecter leur contrat d'API.
     * @param array<int,string> $methodes
     */
    public function methodeNonAutorisee(array $methodes): ReponseHttp
    {
        return new ReponseHttp(405, ['statut' => 'erreur', 'message' => 'methode non autorisee'], [
            'Allow' => implode(', ', $methodes),
        ]);
    }

    /**
     * Filet : l'imprevu, celui qu'aucun type ne decrit.
     *
     * Redefinissez-le pour coller au format existant -- MAIS GARDEZ LE CODE
     * HTTP : un incident technique rendu en 200 fait croire au succes a tous
     * les appelants qui ne lisent que le statut.
     */
    public function panne(): ReponseHttp
    {
        return $this->echec(500, 'erreur', 'erreur interne');
    }

    /** @param array<string,mixed> $corps */
    final protected function reponse(int $code, array $corps): ReponseHttp
    {
        return new ReponseHttp($code, $corps);
    }

    /**
     * Forme d'erreur par defaut : {"statut": …, "message": …}.
     *
     * Si votre API existante en rend une autre, n'utilisez pas ce raccourci
     * et construisez la reponse avec reponse(). Le format existant prime
     * TOUJOURS sur la convention de la bibliotheque : des tiers le lisent.
     */
    protected function echec(int $code, string $statut, string $message): ReponseHttp
    {
        return $this->reponse($code, ['statut' => $statut, 'message' => $message]);
    }
}

