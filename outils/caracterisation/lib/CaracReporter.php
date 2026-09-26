<?php
/**
 * Affichage console et comparaison des resultats.
 *
 * Compatible PHP 7.0 strict.
 */
final class CaracReporter
{
    /** @var bool */
    private $color;
    /** @var int */
    private $passed = 0;
    /** @var int */
    private $failed = 0;
    /** @var int */
    private $skipped = 0;
    /** @var string[] */
    private $failures = array();

    public function __construct($color = true)
    {
        // Pas de couleurs sous cmd.exe classique, qui ne les interprete pas.
        $this->color = $color && (DIRECTORY_SEPARATOR !== '\\' || getenv('ANSICON') !== false || getenv('WT_SESSION') !== false);
    }

    public function title($text)
    {
        echo PHP_EOL . $this->paint($text, '1') . PHP_EOL;
        echo str_repeat('-', 72) . PHP_EOL;
    }

    public function pass($name, $detail = '')
    {
        $this->passed++;
        echo '  ' . $this->paint('OK  ', '32') . $name . ($detail !== '' ? '  ' . $detail : '') . PHP_EOL;
    }

    public function fail($name, array $differences)
    {
        $this->failed++;
        $this->failures[] = $name;
        echo '  ' . $this->paint('ECHEC', '31') . ' ' . $name . PHP_EOL;
        foreach ($differences as $line) {
            echo '        ' . $line . PHP_EOL;
        }
    }

    public function skip($name, $reason)
    {
        $this->skipped++;
        echo '  ' . $this->paint('IGNORE', '33') . ' ' . $name . '  (' . $reason . ')' . PHP_EOL;
    }

    public function info($text)
    {
        echo '  ' . $text . PHP_EOL;
    }

    /** @return int code de sortie du processus */
    public function summary()
    {
        echo PHP_EOL . str_repeat('=', 72) . PHP_EOL;
        echo sprintf(
            'Conforme : %d    Divergent : %d    Ignore : %d',
            $this->passed,
            $this->failed,
            $this->skipped
        ) . PHP_EOL;

        if ($this->failed > 0) {
            echo PHP_EOL . $this->paint('Cas divergents :', '31') . PHP_EOL;
            foreach ($this->failures as $name) {
                echo '  - ' . $name . PHP_EOL;
            }
            echo PHP_EOL
                . 'Une divergence signifie que le contrat expose aux consommateurs a change.' . PHP_EOL
                . 'Soit la modification est involontaire et doit etre corrigee, soit elle est' . PHP_EOL
                . 'assumee et il faut reenregistrer la reference avec : php run.php record' . PHP_EOL;
        }
        echo str_repeat('=', 72) . PHP_EOL;

        return $this->failed > 0 ? 1 : 0;
    }

    /**
     * Compare reference et observation, et decrit chaque ecart.
     *
     * @param array $expected
     * @param array $actual
     * @return string[] liste vide si identiques
     */
    public static function diff(array $expected, array $actual)
    {
        $differences = array();

        if ($expected['status'] !== $actual['status']) {
            $differences[] = sprintf('code HTTP   attendu %d, obtenu %d', $expected['status'], $actual['status']);
        }

        $expectedBody = $expected['body'];
        $actualBody   = $actual['body'];

        if ($expectedBody['shape'] !== $actualBody['shape']) {
            $differences[] = sprintf(
                'forme JSON  attendu %s, obtenu %s',
                $expectedBody['shape'],
                $actualBody['shape']
            );
        }

        if ($expectedBody['value'] !== $actualBody['value']) {
            $differences[] = 'corps       attendu ' . self::render($expectedBody['value']);
            $differences[] = '            obtenu  ' . self::render($actualBody['value']);
        }

        if (isset($expected['content_type'], $actual['content_type'])
            && $expected['content_type'] !== $actual['content_type']) {
            $differences[] = sprintf(
                'Content-Type attendu "%s", obtenu "%s"',
                $expected['content_type'],
                $actual['content_type']
            );
        }

        return $differences;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function render($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '(non serialisable)';
        }
        return strlen($json) > 600 ? substr($json, 0, 600) . ' ...(tronque)' : $json;
    }

    /**
     * @param string $text
     * @param string $code
     * @return string
     */
    private function paint($text, $code)
    {
        return $this->color ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
    }
}

