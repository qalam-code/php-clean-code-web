# Banc d'acceptation — API de paiement

Envoie de vraies requêtes HTTP à l'API et compare chaque réponse — **code HTTP
et corps** — à ce que le contrat publié annonce.

Une divergence est un **défaut**, pas une dérive à enregistrer.

**67 cas** sur les 7 endpoints : 63 sûrs, 3 mutants, 1 externe.

---

## Autonome

Ce dossier ne dépend de **rien** :

- ni de Composer, ni d'un `vendor/`, ni d'un autoloader ;
- ni du code de l'API — il ne charge aucun de ses fichiers ;
- ni de son emplacement — copiez-le où vous voulez, il fonctionnera.

Il lui faut **PHP en ligne de commande avec l'extension curl**, et rien d'autre.

```
php -v
php -m | findstr curl
```

Écrit en **PHP 7.0 strict** : pas de types nullables, pas de `void`, pas
d'opérateur de fusion null. Il doit pouvoir tourner avec le même binaire que la
production.

---

## Installation

```
cd C:\wamp64\www\operation\test_api_paiement
copy config.sample.php config.php
```

Puis renseignez `config.php` : l'URL visée, un compte de `utilisateur_api`, et
les fixtures. La requête SQL qui trouve chaque fixture est en commentaire à côté
d'elle.

**`config.php` ne doit jamais être partagé** — identifiants et références
d'abonnés réels. Le `.htaccess` fourni interdit déjà l'accès web au dossier ;
gardez-le.

Une fixture laissée vide met les cas qui en dépendent en `IGNORE` plutôt qu'en
échec. Vous pouvez démarrer avec deux ou trois valeurs et compléter ensuite.

---

## Utilisation

```
php run.php                  les 63 cas sûrs
php run.php --liste          inventaire, sans rien envoyer
php run.php --verbeux        affiche aussi le corps envoyé et le corps reçu
```

| Option | Effet |
|---|---|
| `--inclure=mutant` | ajoute les 3 cas qui **écrivent en base** |
| `--inclure=mutant,externe --confirmer-externe` | ajoute l'achat de volume réel |
| `--filtre=<texte>` | ne garde que les cas dont le nom contient `<texte>` |
| `--cas=nom1,nom2` | ne lance que ces cas |
| `--je-vise-la-production` | lève le garde-fou d'environnement (voir plus bas) |
| `--sans-couleur` | désactive les codes ANSI (utile sous `cmd.exe`) |

Code de sortie : **0** si tout est conforme, **1** s'il y a une divergence,
**2** si le banc refuse de partir. Utilisable tel quel dans un script.

---

## Choisir l'environnement

Une seule ligne de `config.php` décide :

```php
'base_url' => 'https://flexoperationdev.ddns.net/api_paiement',   // dev
'base_url' => 'https://flexoperation.ddns.net/api_paiement',      // production
```

C'est la contrepartie de l'autonomie du dossier : plus rien ne le rattache à un
serveur, donc plus rien ne l'empêche de viser le mauvais.

**D'où le garde-fou.** Si l'hôte ne ressemble pas à un environnement d'essai —
il ne contient ni `dev`, ni `test`, ni `recette`, ni `staging`, ni `preprod`, ni
`localhost` — les cas qui modifient des données **refusent de partir** :

```
ARRET. base_url ne ressemble pas a un environnement d essai :
  https://flexoperation.ddns.net/api_paiement

Or ces cas MODIFIENT DES DONNEES :
  - paye_succes (mutant)
  ...
```

Les cas sûrs, eux, se lancent partout : ils ne modifient rien, et éprouver le
contrat de la production a du sens.

La reconnaissance porte sur le **nom d'hôte** seulement — un chemin qui
contiendrait `dev` ne dit rien du serveur qui répond.

---

## Les trois niveaux de risque

| Niveau | Nombre | Effet | Lancé par défaut |
|---|---|---|---|
| `sur` | 63 | ne modifie rien, rejouable à volonté | oui |
| `mutant` | 3 | **écrit en base** et consomme sa fixture | non |
| `externe` | 1 | **crédite un vrai compteur d'eau** | non |

`paye_succes`, `annule_succes` et `confirme_succes` consomment leur fixture :
rejoués tels quels, ils basculent sur le chemin « déjà payé / déjà annulé / déjà
confirmé ». Renouvelez les trois fixtures avant chaque passage, ou restaurez un
instantané de la base.

`achat_succes` appelle SHMeters ou Stronpower selon le compteur. Le banc refuse
de partir sans `--confirmer-externe`, et ce garde-fou n'est pas une formalité :
ce qui est consommé l'est pour de bon.

---

## Ce que couvrent les 67 cas

| Groupe | Cas | Ce qui est éprouvé |
|---|---|---|
| `routeur` | 2 | chemin inconnu, mauvaise méthode → 404 JSON, jamais de page HTML |
| `login` | 5 | succès, trois formes de paramètre manquant, identifiants faux |
| `authentification` | **30** | les 6 endpoints protégés × 5 dégradations du jeton |
| `get-factures` | 6 | paramètre manquant, abonné inconnu, sans facture, formats long et court, `id_user` ignoré |
| `paye-facture` | 5 | paramètres, facture inconnue, déjà payée, succès |
| `annule-paiement` | 4 | paramètre, référence inconnue, déjà annulée, succès |
| `achat-volume` | 5 | paramètres, abonné inconnu, sans compteur prépayé, succès |
| `confirme-transc-volume` | 5 | paramètres, identifiant inconnu, déjà confirmée, succès |
| `verif-transaction` | 5 | paramètre, référence inconnue, annulée, succès, idempotence |

**Les 30 cas d'authentification sont le cœur du banc.** Ils envoient à chacun
des six endpoints protégés un jeton absent, un en-tête sans `Bearer`, un jeton
malformé, un jeton à la signature altérée, puis un jeton expiré — et vérifient
que les six répondent **de la même manière**. Une divergence ici obligerait
chaque consommateur à traiter vos endpoints séparément.

Le jeton expiré est le seul qui exige `jwt_secret` : l'API vérifie la signature
**avant** l'expiration, donc un jeton périmé doit être correctement signé pour
atteindre ce contrôle. Les quatre autres dégradations se construisent à partir
d'un jeton valide, sans connaître le secret. Sans `jwt_secret`, ces six cas
s'affichent en `IGNORE`.

> Si la seule valeur de `jwt_secret` qui fonctionne est littéralement `SECRET`,
> c'est que `JWT_SECRET` n'est pas défini dans le `.htaccess` du serveur — et
> alors n'importe qui peut forger un jeton pour n'importe quel compte.

---

## Lire un échec

```
  ECHEC  paye_succes
           corps
             attendu : {"statut":"succes","message":"facure payee avec succes"}
             obtenu  : {"statut":"succes","message":"facture payee avec succes"}
           "facure" sans le t : faute d'origine, conservee car des tiers la comparent.
```

L'écart est affiché en entier, attendu puis obtenu, suivi de la note du cas — ce
qu'il prouve, et pourquoi l'attente est ce qu'elle est.

Pour rejouer un seul cas avec le détail des échanges :

```
php run.php --cas=paye_succes --verbeux
```

---

## Les attentes viennent du contrat, fautes comprises

`cas.php` attend les libellés **tels qu'ils sont publiés**, bizarreries
historiques incluses :

- `facure payee avec succes` — sans le « t », depuis les premières versions ;
- `Transaction deja annule` — majuscule, pas de « e » final, alors que
  `/paye-facture` écrit en minuscules ;
- `Parametre manquant` avec une majuscule sur le **seul** `/get-factures` ;
- `/get-factures` rend un **tableau nu**, `/login` un objet sans `statut`,
  `/achat-volume` un objet à plat sans `message` ;
- la clé `date_limit`, sans « e », alors que la colonne s'appelle `date_limite`.

Ces libellés sont consommés par des services tiers. **Si un cas échoue sur l'un
d'eux, c'est le code qui a bougé, pas l'attente qui est fausse.**

L'ordre des clés est vérifié lui aussi, pour les réponses comparées par jeu de
clés : il fait partie du contrat dès qu'un consommateur analyse la réponse avec
du code écrit à la main.

---

## Acceptation ≠ caractérisation

| | Acceptation (ici) | Caractérisation (`api_paiement/tests/characterization/`) |
|---|---|---|
| Référence | le contrat documenté | ce que l'ancien code répondait |
| Une divergence signifie | le code ne respecte plus le contrat | le refactoring a changé un comportement |
| Sert à | valider une livraison | ne rien casser pendant un refactoring |

Les deux sont complémentaires, et ce banc-ci est celui qui reste utile une fois
le refactoring terminé.

---

## Notes techniques

- `run.php` refuse de s'exécuter autrement qu'en ligne de commande : servi par
  Apache, il exposerait le contenu de `config.php`.
- Le `.htaccess` fourni interdit l'accès web au dossier entier.
- Les redirections ne sont **pas** suivies : une 301 vers `http://` ferait
  passer le jeton en clair et masquerait une erreur de configuration.
- **`cas.php` est en UTF-8 sans BOM** : il contient `Paramètres invalides`, avec
  son accent. Enregistré en ANSI, tous les cas `/achat-volume` et
  `/confirme-transc-volume` échoueraient.
- `verifier_certificat` ne passe à `false` que si le certificat de
  l'environnement visé est auto-signé. Jamais contre la production.

