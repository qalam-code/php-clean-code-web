<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use Throwable;
use UnexpectedValueException;

/** Frontiere HTTP : transforme les incidents inattendus en reponse HTML sure. */
final class AiguillageWeb
{
    private $fabriqueRouteur;
    private $journaliseur;

    /**
     * @param callable():RouteurWeb $fabriqueRouteur construit les routes pour la requete courante
     * @param callable(Throwable):void|null $journaliseur
     */
    public function __construct(callable $fabriqueRouteur, $journaliseur = null)
    {
        if ($journaliseur !== null && !is_callable($journaliseur)) {
            throw new \InvalidArgumentException('Le journaliseur doit etre appelable.');
        }
        $this->fabriqueRouteur = $fabriqueRouteur;
        $this->journaliseur = $journaliseur;
    }

    public function servir(Requete $requete): ReponseHtml
    {
        try {
            $fabrique = $this->fabriqueRouteur;
            $routeur = $fabrique();
            if (!$routeur instanceof RouteurWeb) {
                throw new UnexpectedValueException('La fabrique doit retourner un RouteurWeb.');
            }
            $reponse = $routeur->servir($requete);
            if (!$reponse instanceof ReponseHtml) {
                throw new UnexpectedValueException('Le routeur web doit retourner une ReponseHtml.');
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
            // Un journaliseur en panne ne doit pas empecher la reponse 500.
            error_log('Echec du journaliseur web : ' . $erreurJournal->getMessage());
        }
    }
}