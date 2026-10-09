<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Application;

use InvalidArgumentException;
use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;

/** Garde un message en session jusqu'à sa lecture sur la requête suivante. */
final class MessagesFlash
{
    private const CLE_SESSION = '_qalam_code_web_flash';
    private $session;

    public function __construct(StockageSession $session)
    {
        $this->session = $session;
    }

    /** Enregistre ou remplace le message associé à cette clé. */
    public function ajouter(string $cle, string $message)
    {
        $this->verifierCle($cle);
        $messages = $this->messagesSession();
        $messages[$cle] = $message;
        $this->session->ecrire(self::CLE_SESSION, $messages);
    }

    /** Retourne puis efface le message, ou null s'il n'existe pas. */
    public function consommer(string $cle)
    {
        $this->verifierCle($cle);
        $messages = $this->messagesSession();
        if (!isset($messages[$cle]) || !is_string($messages[$cle])) {
            return null;
        }

        $message = $messages[$cle];
        unset($messages[$cle]);
        $this->session->ecrire(self::CLE_SESSION, $messages);
        return $message;
    }

    private function messagesSession(): array
    {
        $messages = $this->session->lire(self::CLE_SESSION, []);
        return is_array($messages) ? $messages : [];
    }

    private function verifierCle(string $cle)
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $cle)) {
            throw new InvalidArgumentException('Cle de message flash invalide.');
        }
    }
}
