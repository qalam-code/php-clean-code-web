<?php
/**
 * Normalisation des reponses avant comparaison.
 *
 * Deux modes de comparaison :
 *   - 'full'      : la valeur entiere est comparee, apres masquage des champs
 *                   volatils (token, dates, identifiants generes).
 *                   Reserve aux reponses deterministes, typiquement les erreurs.
 *   - 'structure' : seule la forme est comparee (type JSON, jeu de cles, type
 *                   de chaque feuille). Reserve aux reponses dont le contenu
 *                   depend de l'etat de la base a l'instant T.
 *
 * Compatible PHP 7.0 strict.
 */
final class CaracNormalizer
{
    const MASK = '<<MASQUE>>';

    /** @var string[] cles dont la valeur est volatile */
    private $maskKeys;

    public function __construct(array $maskKeys = array())
    {
        $this->maskKeys = array_map('strtolower', $maskKeys);
    }

    /**
     * @param string $rawBody
     * @param string $mode 'full' ou 'structure'
     * @return array forme comparable et serialisable
     */
    public function normalize($rawBody, $mode = 'full')
    {
        $trimmed = trim($rawBody);
        $decoded = json_decode($trimmed, true);

        // json_decode renvoie null aussi bien pour l'entree "null" que sur erreur.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'json'  => false,
                'shape' => 'non-json',
                'value' => $this->collapseWhitespace($trimmed),
            );
        }

        $value = ($mode === 'structure')
            ? $this->structureOf($decoded)
            : $this->mask($decoded);

        return array(
            'json'  => true,
            'shape' => $this->shapeOf($decoded),
            'value' => $value,
        );
    }

    /**
     * Distingue un tableau JSON nu d'un objet JSON. C'est une garantie du
     * contrat actuel : /get-factures renvoie un tableau nu, les autres
     * endpoints renvoient un objet.
     *
     * @param mixed $value
     * @return string
     */
    private function shapeOf($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (!is_array($value)) {
            return 'scalar:' . gettype($value);
        }
        return $this->isList($value) ? 'list' : 'object';
    }

    /**
     * @param array $value
     * @return bool
     */
    private function isList(array $value)
    {
        if ($value === array()) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * Remplace la valeur des cles volatiles, en profondeur.
     *
     * @param mixed $value
     * @return mixed
     */
    private function mask($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = array();
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $this->maskKeys, true)) {
                $out[$key] = $item === null ? null : self::MASK;
                continue;
            }
            $out[$key] = $this->mask($item);
        }
        return $out;
    }

    /**
     * Reduit une valeur a sa signature structurelle. Pour une liste, la
     * signature retenue est l'union des cles de tous les elements, ce qui
     * detecte l'apparition ou la disparition d'un champ quel que soit le
     * nombre d'elements renvoyes.
     *
     * @param mixed $value
     * @return mixed
     */
    private function structureOf($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (!is_array($value)) {
            return gettype($value);
        }

        if ($this->isList($value)) {
            if ($value === array()) {
                return array('__liste_vide__');
            }
            $union = array();
            foreach ($value as $element) {
                $signature = $this->structureOf($element);
                if (is_array($signature)) {
                    $union = array_merge($union, $signature);
                } else {
                    $union['__scalaire__'] = $signature;
                }
            }
            ksort($union);
            return array('__liste_de__' => $union);
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = $this->structureOf($item);
        }
        ksort($out);
        return $out;
    }

    /**
     * @param string $text
     * @return string
     */
    private function collapseWhitespace($text)
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text);
        return $collapsed === null ? $text : trim($collapsed);
    }
}

