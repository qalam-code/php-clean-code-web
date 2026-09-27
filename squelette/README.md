# Squelette

Projet complet et fonctionnel, a copier pour demarrer. Deux endpoints :
`POST /login` et `GET /facture`.

Il n'est pas la pour etre garde tel quel : il est la pour montrer ou va quoi.

---

## Mise en route

```bash
composer config --global repositories.qalam-skeleton vcs https://github.com/qalam-code/php-clean-code-skeleton
composer create-project qalam-code/php-clean-code-skeleton mon-projet
cd mon-projet
php tests/architecture/equivalence.php
```

Après enregistrement du squelette sur Packagist, la forme courte sera
`composer create-project qalam-code/php-clean-code-skeleton mon-projet`.

Puis, dans l'ordre :

1. **Espace de noms** — remplacez `App\Exemple\` par le votre dans `src/`,
   `autoload.php` et `composer.json`.
2. **Configuration** — `public/.htaccess` : `RewriteBase`, puis les `SetEnv`.
   **`JWT_SECRET` n'a pas de valeur par defaut**, et c'est voulu : une valeur
   de repli finit toujours en production, et alors n'importe qui peut forger un
   jeton valide.
3. **Domaine** — remplacez `Facture` par vos entites, `ErreurFacturation` par
   vos types d'erreur.
4. **Routes** — declarez-les dans `src/Fabrique.php`.

---

## Ou va quoi

| Vous ecrivez…                              | …dans                          |
|--------------------------------------------|--------------------------------|
| une notion metier, ses invariants          | `src/Domain/Entite/`           |
| ce dont le domaine a besoin (interfaces)   | `src/Domain/Contrat/`          |
| vos types d'erreur                         | `src/Domain/ErreurXxx.php`     |
| une operation complete (« payer »)         | `src/Application/`             |
| du SQL, un appel reseau, un fichier        | `src/Infrastructure/`          |
| l'extraction des parametres HTTP           | `src/Presentation/Controleur/` |
| les codes HTTP et le format des reponses   | `src/Presentation/Presentateur/`|
| le cablage, les routes                     | `src/Fabrique.php`             |

**En cas de doute** : posez-vous la question « est-ce que ca survivrait si on
remplacait MySQL par des fichiers, et HTTP par une ligne de commande ? ». Si
oui, c'est du domaine ou de l'application. Sinon, c'est de l'infrastructure ou
de la presentation.

---

## Ce que le code d'exemple montre

- `Facture` — un montant **en centimes, entier**. Un `float` ne represente pas
  exactement 0,10 ; additionnez-en assez et le total devient faux.
- `Connecter` — le choix, **ecrit noir sur blanc**, de laisser passer une
  connexion dont la trace n'a pas pu s'ecrire.
- `ConsulterFacture` — l'ordre valider / authentifier / lire. L'inverser
  laisserait un anonyme apprendre quelles factures existent.
- `DepotUtilisateurPdo` — `password_verify`, jamais de comparaison en SQL ; et
  un hachage factice verifie sur compte inconnu, pour que la reponse ne soit
  pas plus rapide.
- `ResolveurDepot` — cinq lignes qui relisent le compte en base a chaque
  requete, pour qu'un jeton emis ce matin ne vaille pas pour un compte
  desactive depuis.
- `Fabrique` — chaque route declare son presentateur ; l'authentification est
  differee.
- `equivalence.php` — dont un tiers verifie le **contrat HTTP** : codes, cles,
  et ordre des cles.

---

## Servir

```
DocumentRoot  ->  public/
```

`public/` ne contient que `index.php` et `.htaccess`. Les sources sont en
dehors : un fichier qui n'est pas servi ne peut pas etre lu par erreur.

Pour essayer sans Apache :

```bash
JWT_SECRET=test BASE_URI= php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/facture?numero=F-1"
# {"statut":"erreur","message":"token introuvable"}
```

