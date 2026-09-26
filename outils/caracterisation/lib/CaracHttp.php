<?php
/**
 * Client HTTP minimal pour le harnais de caracterisation.
 *
 * Compatible PHP 7.0 strict : pas de types nullables (?T), pas de type de
 * retour void, pas de proprietes typees, pas de fonctions flechees.
 */

final class CaracHttpResponse
{
    /** @var int */
    public $status;
    /** @var array<string,string> */
    public $headers;
    /** @var string */
    public $body;
    /** @var int */
    public $durationMs;
    /** @var string chaine vide si aucune erreur transport */
    public $error;

    public function __construct($status, array $headers, $body, $durationMs, $error)
    {
        $this->status     = (int) $status;
        $this->headers    = $headers;
        $this->body       = (string) $body;
        $this->durationMs = (int) $durationMs;
        $this->error      = (string) $error;
    }

    /** @return bool */
    public function failed()
    {
        return $this->error !== '';
    }
}

final class CaracHttpClient
{
    /** @var string */
    private $baseUrl;
    /** @var int */
    private $timeout;

    public function __construct($baseUrl, $timeout = 60)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = (int) $timeout;
    }

    /**
     * @param string $method  GET|POST
     * @param string $path    ex. '/paye-facture'
     * @param array  $query   parametres de query string
     * @param mixed  $body    tableau encode en JSON, ou null
     * @param array  $headers en-tetes additionnels, format 'Nom: valeur'
     * @return CaracHttpResponse
     */
    public function send($method, $path, array $query = array(), $body = null, array $headers = array())
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $responseHeaders = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        // Le contrat porte sur le corps et le code HTTP, pas sur les redirections.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $allHeaders = $headers;
        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            $allHeaders[] = 'Content-Type: application/json';
            $allHeaders[] = 'Content-Length: ' . strlen($encoded);
        }
        if (!empty($allHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$responseHeaders) {
            $length = strlen($line);
            $parts  = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        });

        $start    = microtime(true);
        $rawBody  = curl_exec($ch);
        $duration = (int) round((microtime(true) - $start) * 1000);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($rawBody === false) {
            $rawBody = '';
        }

        return new CaracHttpResponse($status, $responseHeaders, $rawBody, $duration, $error);
    }
}

