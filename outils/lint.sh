#!/bin/sh
# Controle de compatibilite en deux passes, sous une version precise de PHP.
#
#   outils/lint.sh [php] [racine-src] [prefixe-namespace] [version]
#
# Exemple :
#   outils/lint.sh php7.0 src App\\Exemple 7.0
#
# VERIFIEZ LA VERSION AFFICHEE. Lancer ce script avec une version plus
# recente que celle de production ne prouve rien.

set -e

PHP="${1:-php}"
RACINE="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${2:-$RACINE/src}"
PREFIXE="${3:-PhpCleanCode}"
CIBLE="${4:-7.4}"

if ! command -v "$PHP" >/dev/null 2>&1 && [ ! -x "$PHP" ]; then
    echo "Binaire introuvable : $PHP"
    exit 2
fi

echo "=== Passe 1 : syntaxe ==="
"$PHP" -r 'echo "PHP ", PHP_VERSION, PHP_EOL;'
echo

NB=0
KO=0
for FICHIER in $(find "$SRC" -name '*.php' | sort); do
    NB=$((NB + 1))
    if ! "$PHP" -l "$FICHIER" >/dev/null 2>&1; then
        KO=$((KO + 1))
        echo "ERREUR : $FICHIER"
        "$PHP" -l "$FICHIER" || true
    fi
done

echo "$NB fichiers analyses, $KO en erreur de syntaxe."
echo

[ "$KO" -gt 0 ] && exit 1

echo "=== Passe 2 : liaison des classes ==="
echo '(visibilite, signatures, interfaces -- ce que "php -l" ne voit pas)'
echo
"$PHP" "$RACINE/outils/compat.php" "$SRC" "$PREFIXE" "$CIBLE"

