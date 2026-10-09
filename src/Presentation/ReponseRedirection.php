<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Presentation;

use InvalidArgumentException;

/** Réponse HTTP 303 destinée au parcours de navigation HTML. */
final class ReponseRedirection extends ReponseWeb
{
    public function __construct(string $destination)
    {
        // Une destination locale évite qu'une route ouverte redirige vers un site tiers.
        if ($destination === ''
            || $destination[0] !== '/'
            || strpos($destination, '//') === 0
            || strpos($destination, '\\') !== false
            || preg_match('/[\r\n]/', $destination)
        ) {
            throw new InvalidArgumentException('La destination doit etre une URL locale valide.');
        }

        parent::__construct(303, '', [
            'Location' => $destination,
            'Cache-Control' => 'no-store',
        ]);
    }
}
