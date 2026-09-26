# Harnais de caractérisation

Ce harnais sert à **refactorer un code existant sans rien casser**.

Il ne teste pas ce qui *devrait* se produire : il enregistre ce qui *se produit
aujourd'hui*, c'est-à-dire ce sur quoi les consommateurs tiers se sont alignés.
Toute divergence constatée après une modification est une rupture de contrat —
à corriger, ou à assumer explicitement.

La distinction avec la suite d'équivalence du squelette est nette :

| | Caractérisation | Équivalence |
|---|---|---|
| Interroge | le serveur, par HTTP | les classes, en mémoire |
| Référence | ce que l'ancien code répondait | ce que vous avez décidé |
| Sert à | ne pas casser l'existant | vérifier le comportement voulu |
| Vitesse | quelques secondes | quelques millisecondes |

Les deux sont complémentaires. Sur un code ancien, la caractérisation vient
**en premier** : elle est le filet, et l'équivalence ne prouve rien tant qu'on
ne sait pas ce que l'ancien code faisait.

---

## Prérequis

PHP en ligne de commande avec l'extension `curl`. **Aucune dépendance
Composer**, et c'est délibéré : le harnais doit pouvoir tourner sur le serveur,
avec la version de PHP de la production. Tout est écrit en **PHP 7.0 strict** —
ni types nullables, ni `void` — pour cette seule raison.

```
php -v
php -m | grep curl          # findstr curl sous Windows
```

---

## Installation

```
cp config.sample.php config.php
cp cases.exemple.php  cases.php
```

Puis renseignez `config.php` (URL, compte de test, fixtures) et **remplacez
intégralement `cases.php`** par les endpoints de votre projet.
`cases.exemple.php` est livré rempli, tiré d'un projet réel : gardez-le sous
les yeux, il montre comment se décrivent les cas d'authentification dégradée et
les cas qui modifient la base.

`config.php`, `golden/` et `last-run/` contiennent des identifiants et des
données réelles : **ne les partagez pas.**

---

## Utilisation

```
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

`verify` renvoie `1` en cas de divergence, `0` sinon — utilisable dans un
script.

### Cycle de travail

```
php run.php record          <-- une fois, sur le code AVANT modification
... refactoring ...
php run.php verify          <-- doit rester vert
```

Si une divergence est **voulue** — un bug que vous corrigez sciemment — on
réenregistre le seul cas concerné, et **on écrit pourquoi** :

```
php run.php record --cas=nom_du_cas
```

Un réenregistrement en masse après un échec vide le harnais de tout son sens :
il ne resterait qu'un outil qui confirme ce que le code fait, quoi qu'il fasse.

---

## Niveaux de risque

| Niveau | Effet | Exécuté par défaut |
|---|---|---|
| `sur` | lecture seule, rejouable à volonté | oui |
| `mutant` | **modifie la base** et consomme sa fixture | non |
| `externe` | **appelle un tiers réel** — et ce qui est consommé l'est pour de bon | non |

Les cas sûrs couvrent déjà l'essentiel : tous les codes d'erreur, toutes les
chaînes de message, la forme de chaque réponse, le comportement de
l'authentification.

Un cas mutant **consomme sa fixture** : rejoué tel quel, il bascule sur le
chemin « déjà payé / déjà annulé ». Renouvelez les fixtures avant chaque
passage, ou restaurez un instantané de la base — préférable pour une
comparaison avant/après fiable.

Un cas externe **ne doit jamais être lancé en production.** La double
confirmation est là pour ça :

```
php run.php record --inclure=externe --confirmer-externe
```

---

## Modes de comparaison

| Mode | Ce qui est comparé | Employé pour |
|---|---|---|
| `complet` | la valeur entière, après masquage des champs volatils | réponses déterministes, typiquement les erreurs |
| `structure` | le type JSON, le jeu de clés, le type de chaque feuille | réponses dont le contenu dépend de l'état de la base |

Une liste dont le contenu change à chaque jour ouvré se compare en
`structure` : une enveloppe ajoutée autour du tableau ou une clé renommée est
détectée, un élément de plus ne l'est pas.

Les champs listés dans `masquer` — jeton, horodatage, identifiant généré — sont
remplacés par `<<MASQUE>>` avant comparaison. Sans cela, aucun `verify` ne
serait jamais vert.

---

## Fichiers produits

| Dossier | Contenu |
|---|---|
| `golden/` | la référence, une réponse par cas — **ne pas modifier à la main** |
| `last-run/` | la dernière exécution, avec un extrait du corps brut, pour inspecter une divergence |

---

## Figez les défauts, aussi

C'est le point que l'on comprend de travers une fois sur deux.

Le harnais enregistre le comportement **tel quel, y compris ses bizarreries** :
une faute de frappe dans un message, une majuscule là où les autres endpoints
n'en mettent pas, un endpoint qui renvoie un tableau nu quand tous les autres
renvoient une enveloppe.

Ces défauts sont du contrat. Des consommateurs écrits il y a des années
comparent peut-être la chaîne exacte. Les corriger *est* une rupture — qui
s'arbitre avec les consommateurs, pas au détour d'un refactoring.

Réparez-les plus tard, délibérément, en prévenant. Pas pendant.

