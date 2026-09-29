<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use Throwable;
use UnexpectedValueException;

/** Frontiere HTTP : sert les actifs puis route les pages, avec erreurs sures. */
final class AiguillageWeb
{
    private $fabriqueRouteur;
    private $journaliseur;
    private $serveurActifs;

    /**
     * @param callable():RouteurWeb $fabriqueRouteur
     * @param callable(Throwable):void|null $journaliseur
     * @param ServeurActifsVue|null $serveurActifs
     */
    public function __construct(callable $fabriqueRouteur, $journaliseur = null, $serveurActifs = null)
    {
        if ($journaliseur !== null && !is_callable($journaliseur)) {
            throw new \InvalidArgumentException('Le journaliseur doit etre appelable.');
        }
        if ($serveurActifs !== null && !($serveurActifs instanceof ServeurActifsVue)) {
            throw new \InvalidArgumentException('Le serveur d actifs doit etre un ServeurActifsVue.');
        }
        $this->fabriqueRouteur = $fabriqueRouteur;
        $this->journaliseur = $journaliseur;
        $this->serveurActifs = $serveurActifs;
    }

    public function servir(Requete $requete): ReponseWeb
    {
        try {
            if ($this->serveurActifs !== null) {
                $reponseActif = $this->serveurActifs->servir($requete);
                if ($reponseActif !== null) {
                    return $reponseActif;
                }
            }
            $fabrique = $this->fabriqueRouteur;
            $routeur = $fabrique();
            if (!$routeur instanceof RouteurWeb) {
                throw new UnexpectedValueException('La fabrique doit retourner un RouteurWeb.');
            }
            $reponse = $routeur->servir($requete);
            if (!$reponse instanceof ReponseWeb) {
                throw new UnexpectedValueException('Le routeur web doit retourner une ReponseWeb.');
            }
            return $reponse;
        } catch (Throwable $erreur) {
            $this->journaliser($erreur);
            return new ReponseHtml(
                500,
                '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur</title>'
                    . '<h1>500 - Erreur interne</h1><p>Une erreur inattendue est survenue.</p></html>'
            );
        }
    }

    private function journaliser(Throwable $erreur)
    {
        try {
            if ($this->journaliseur === null) {
                error_log((string) $erreur);
                return;
            }
            $journaliseur = $this->journaliseur;
            $journaliseur($erreur);
        } catch (Throwable $erreurJournal) {
            error_log('Echec du journaliseur web : ' . $erreurJournal->getMessage());
        }
    }
}