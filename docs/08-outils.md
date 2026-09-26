# 8. Les outils

`outils/` — quatre outils, dont deux écrits en **PHP 7.0 strict** pour pouvoir
tourner sur la production la plus ancienne.

```
outils/
├── verification.php       auto-test de la bibliothèque
├── compat.php             contrôle de liaison des classes
├── lint.bat / lint.sh     syntaxe + liaison, version paramétrable
└── caracterisation/       harnais HTTP
    ├── run.php
    ├── config.sample.php
    ├── cases.exemple.php
    ├── README.md
    └── lib/
        ├── CaracHttp.php
        ├── CaracJwt.php
        ├── CaracNormalizer.php
        └── CaracReporter.php
```

---

## 8.1 `verification.php` — l'auto-test

```bash
php outils/verification.php
```

**36 contrôles** sur la bibliothèque elle-même, répartis en huit sections :

| Section | Ce qui est éprouvé |
|---|---|
| JetonJwt — accepté | aller-retour de la charge, `iat`, format base64url |
| JetonJwt — refusé | chaîne quelconque, charge modifiée, `alg:none`, autre secret, expiration, absence d'`exp`, secret vide |
| Authentificateur | `Bearer`, casse, en-tête absent ou vide, compte supprimé, mémoïsation |
| Paresse | rien de construit trop tôt, aucune connexion avant `pdo()` |
| Surveillance | épuisement par étapes, effet réel de la marge |
| ReponseHttp | ordre des clés, accents non échappés, journal qui ne lève pas |
| Aiguillage | route servie, `ErreurMetier` traduite, chemin inconnu, inventaire |
| Déclarabilité | **visibilité du constructeur par réflexion**, fabriques nommées |

> **⚠ Lancez-le avec le PHP de votre production**, pas seulement avec celui de
> votre poste. Une bibliothèque verte sous PHP 8 et fatale sous PHP 7.0, cela
> s'est déjà vu — c'est même la raison d'être du dernier contrôle.

**La section « Déclarabilité » mérite un mot.** Elle applique par réflexion la
règle stricte de PHP 7.0 sur la visibilité des constructeurs, **quelle que soit
la version qui l'exécute** :

```php
$constructeur = (new ReflectionClass(ErreurMetier::class))->getConstructor();
$v->vrai($constructeur !== null && $constructeur->isPublic(),
    'ErreurMetier::__construct est publique (obligatoire dès PHP 7.0)');
```

C'est le seul moyen de se prémunir de ce piège depuis un poste plus récent que la
production.

---

## 8.2 `compat.php` — le contrôle de liaison

```bash
php outils/compat.php [racine-src] [prefixe-namespace] [version-cible]

# Exemples
C:\wamp64\bin\php\php7.0.33\php.exe outils\compat.php src App\Paiement 7.0
php outils/compat.php squelette/src App\Exemple 7.4
```

**Pourquoi ce script existe.** `php -l` ne vérifie que la **syntaxe** : il ne
déclare pas les classes, et ne peut donc voir aucune erreur de **liaison** —
visibilité réduite sur une méthode héritée, signature incompatible avec le
parent, interface implémentée à moitié, constante absente.

Ce script déclare toutes les classes trouvées et laisse PHP se plaindre.

**Un échec de liaison est fatal** : le script s'arrête en nommant la classe
fautive — exactement le message que produirait le serveur. Ce n'est pas une
fragilité, c'est le comportement voulu.

**Il signale aussi les fichiers qui ne contiennent pas le type attendu** :

```
  MANQUANT  App\Exemple\Domain\Facture
```

Le plus souvent, PSR-4 n'est pas respecté — le fichier ne porte pas le nom de sa
classe, ou n'est pas dans le bon dossier.

**Il avertit s'il tourne sous une version plus récente que la cible**, et le dit
sans ambiguïté : *« Ce contrôle ne prouve donc rien. »*

**Écrit en PHP 5 volontairement** : il doit pouvoir démarrer sous n'importe
quelle version pour avoir une chance de dire ce qui ne va pas.

**Code de sortie** : `0` si tous les types se déclarent, `1` sinon.

---

## 8.3 `lint.bat` / `lint.sh` — les deux passes

```bash
# Unix
outils/lint.sh php7.4 src PhpCleanCode 7.4

# Windows
outils\lint.bat C:\wamp64\bin\php\php7.0.33\php.exe src App\Paiement 7.0
```

Quatre arguments, tous optionnels : le binaire PHP, la racine des sources, le
préfixe de namespace, la version cible.

| Passe | Outil | Ce qu'elle voit |
|---|---|---|
| 1 | `php -l` sur chaque fichier | erreurs de syntaxe |
| 2 | `compat.php` | erreurs de liaison |

**La seconde est celle qui compte.** La première ne fait que s'assurer que la
seconde pourra tourner.

**Vérifiez la version affichée.** Lancer ce script avec PHP 8 sur un projet
destiné à PHP 7.0 ne prouve rien du tout — et le script vous le dit, mais il ne
peut pas vous en empêcher.

---

## 8.4 Le harnais de caractérisation

`outils/caracterisation/` sert à un cas précis : **refactorer un code existant
sans rien casser**.

### Le principe

Celui de Michael Feathers : avant de toucher à du code dont personne ne connaît
plus le comportement exact, on l'interroge tel qu'il est et **on enregistre ce
qu'il répond** — y compris ses bizarreries. Ces réponses deviennent la
référence. Le refactoring est réussi quand le nouveau code rend exactement les
mêmes.

Cela ne teste pas ce que le code *devrait* faire. Cela vérifie qu'il fait
toujours *ce qu'il faisait*. Les deux questions sont différentes, et la seconde
est la seule qui protège vos consommateurs.

### Caractérisation et équivalence

| | Caractérisation | Équivalence |
|---|---|---|
| Interroge | le serveur, par HTTP | les classes, en mémoire |
| Référence | ce que l'ancien code répondait | ce que vous avez décidé |
| Sert à | ne pas casser l'existant | vérifier le comportement voulu |
| Vitesse | quelques secondes | quelques millisecondes |

Sur un code ancien, la caractérisation vient **en premier** : elle est le filet,
et l'équivalence ne prouve rien tant qu'on ne sait pas ce que l'ancien code
faisait.

### Installation

```bash
cp outils/caracterisation/config.sample.php outils/caracterisation/config.php
cp outils/caracterisation/cases.exemple.php  outils/caracterisation/cases.php
```

Puis renseignez `config.php` (URL, compte de test, fixtures) et **remplacez
intégralement `cases.php`** par les endpoints de votre projet.

> **⚠** `config.php`, `golden/` et `last-run/` contiennent des identifiants et
> des données réelles. Ne les partagez pas.

### Utilisation

```bash
php run.php list       liste les cas et leur niveau de risque
php run.php record     enregistre la référence depuis le serveur
php run.php verify     compare le serveur à la référence
```

| Option | Effet |
|---|---|
| `--inclure=mutant,externe` | ajoute les cas qui modifient la base ou appellent un tiers |
| `--confirmer-externe` | obligatoire en plus de `--inclure=externe` |
| `--filtre=<texte>` | ne garde que les cas dont le nom contient `<texte>` |
| `--cas=nom1,nom2` | ne lance que ces cas |
| `--sans-couleur` | désactive les codes ANSI (utile sous `cmd.exe`) |

`verify` renvoie `1` en cas de divergence, `0` sinon.

### Le cycle de travail

```
php run.php record          <-- une fois, sur le code AVANT modification
... refactoring ...
php run.php verify          <-- doit rester vert
```

Si une divergence est **voulue** — un bug corrigé sciemment — on réenregistre le
seul cas concerné, et **on écrit pourquoi** :

```bash
php run.php record --cas=nom_du_cas
```

> **⚠** Un réenregistrement en masse après un échec vide le harnais de tout son
> sens : il ne resterait qu'un outil qui confirme ce que le code fait, quoi qu'il
> fasse.

### Les trois niveaux de risque

| Niveau | Effet | Exécuté par défaut |
|---|---|---|
| `sur` | lecture seule, rejouable à volonté | oui |
| `mutant` | **modifie la base** et consomme sa fixture | non |
| `externe` | **appelle un tiers réel** — ce qui est consommé l'est pour de bon | non |

Un cas mutant **consomme sa fixture** : rejoué tel quel, il bascule sur le chemin
« déjà payé / déjà annulé ». Renouvelez les fixtures avant chaque passage, ou
restaurez un instantané de la base.

Un cas externe **ne doit jamais être lancé en production.** La double
confirmation est là pour ça.

### Les deux modes de comparaison

| Mode | Ce qui est comparé | Employé pour |
|---|---|---|
| `complet` | la valeur entière, après masquage des champs volatils | réponses déterministes, typiquement les erreurs |
| `structure` | le type JSON, le jeu de clés, le type de chaque feuille | réponses dont le contenu dépend de l'état de la base |

Une liste dont le contenu change chaque jour se compare en `structure` : une
enveloppe ajoutée autour du tableau ou une clé renommée est détectée, un élément
de plus ne l'est pas.

Les champs listés dans `masquer` — jeton, horodatage, identifiant généré — sont
remplacés par `<<MASQUE>>` avant comparaison. Sans cela, aucun `verify` ne serait
jamais vert.

### Les quatre classes de `lib/`

| Classe | Rôle |
|---|---|
| `CaracHttp` | client cURL : construit la requête, suit les en-têtes, rend corps et code |
| `CaracJwt` | forge les jetons **dégradés** — expiré, mal signé, malformé — sans dépendre du serveur |
| `CaracNormalizer` | masque les champs volatils, réduit une réponse à sa structure |
| `CaracReporter` | affiche le tableau de résultats, avec ou sans couleur |

`CaracJwt` reproduit volontairement l'algorithme de l'implémentation JWT du
projet : c'est le seul moyen de tester le **refus** d'un jeton invalide sans
demander au serveur de fabriquer un jeton invalide.

### Figez les défauts, aussi

C'est le point que l'on comprend de travers une fois sur deux.

Le harnais enregistre le comportement **tel quel, y compris ses bizarreries** :
une faute de frappe dans un message, une majuscule là où les autres endpoints
n'en mettent pas, un endpoint qui renvoie un tableau nu quand tous les autres
renvoient une enveloppe.

**Ces défauts sont du contrat.** Des consommateurs écrits il y a des années
comparent peut-être la chaîne exacte. Les corriger *est* une rupture — qui
s'arbitre avec les consommateurs, pas au détour d'un refactoring.

Réparez-les plus tard, délibérément, en prévenant. Pas pendant.

