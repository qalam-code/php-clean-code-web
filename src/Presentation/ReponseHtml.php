<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Presentation;

/** Reponse HTTP HTML avec son type de contenu par defaut. */
final class ReponseHtml extends ReponseWeb
{
    /** @param array<string,string> $entetes */
    public function __construct(int $code, string $corps, array $entetes = [])
    {
        // Toute reponse HTML reçoit ce type par defaut ; une route peut le remplacer explicitement.
        $entetesHtml = ['Content-Type' => 'text/html; charset=utf-8'];
        foreach ($entetes as $nom => $valeur) {
            // Les noms d'en-tete ne sont pas sensibles a la casse : eviter deux Content-Type concurrents.
            if (strtolower($nom) === 'content-type') {
                $entetesHtml['Content-Type'] = $valeur;
            } else {
                $entetesHtml[$nom] = $valeur;
            }
        }
        parent::__construct($code, $corps, $entetesHtml);
    }
}
