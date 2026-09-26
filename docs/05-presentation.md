# 5. La couche Presentation

`src/Presentation/` — **quatre fichiers**. C'est ici que vit le contrat de
l'API : les codes HTTP, les noms de clés, les libellés.

```
Presentation/
├── ReponseHttp.php
├── Authentificateur.php
└── Presentateur/
    ├── PresentateurAbstrait.php
    └── PresentateurCommun.php
```

---

## 5.1 `ReponseHttp`

**Signature**

```php
final class ReponseHttp
{
    public function __construct(int $code, array $corps, array $entetes = []);
    public function code(): int;
    public function corps(): array;
    public function entetes(): array;
    public function json(): string;
    public function envoyer(): void;
}
```

**Rôle** — Un code, un corps, des en-têtes. Rien d'autre.

**Pourquoi un objet plutôt qu'un `echo`** — Son intérêt est de rendre la réponse
**inspectable** : un test lit `code()` et `corps()` directement, sans capturer
une sortie ni analyser du JSON. C'est ce qui permet de vérifier un contrat d'API
— les codes et les clés rendues — sans serveur web.

> **⚠ L'ordre des clés du tableau est l'ordre du JSON.** Quand des tiers
> consomment déjà l'API, il fait partie du contrat au même titre que les noms de
> clés : ne le changez pas en réorganisant du code. `Verificateur::reponseEgale()`
> le surveille explicitement. Voir [`11-pieges.md`](11-pieges.md), piège n° 7.

**`json()`** encode avec `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`. Sans
le premier, « réglée » part en séquences `\uXXXX`, illisibles dans les journaux
comme en débogage.

**`envoyer()` est le seul endroit du code qui écrit sur la sortie.** Tout le
reste retourne des objets ; un cas d'usage ou un dépôt qui fait `echo` est un cas
d'usage qu'on ne pourra plus tester. La méthode vérifie `headers_sent()` avant
d'écrire les en-têtes, pour ne pas provoquer un avertissement qui polluerait le
corps JSON.

---

## 5.2 `Authentificateur`

**Signature**

```php
final class Authentificateur implements AuthentificationInterface
{
    public function __construct(
        Requete $requete,
        JetonInterface $jetons,
        ResolveurActeurInterface $resolveur
    );
    public function identifier(): Identite;   // @throws ErreurMetier
}
```

**Rôle** — Passer de l'en-tête `Authorization` à une `Identite`.

**Il est dans `Presentation` parce qu'il lit HTTP.** L'en-tête est un détail de
transport ; le cas d'usage, lui, ne connaît que le port
`Application\Port\AuthentificationInterface`.

**Trois étapes, dans cet ordre, et aucune n'est optionnelle** :

1. extraire le jeton de l'en-tête ;
2. vérifier signature et expiration (`JetonInterface`) ;
3. retrouver l'utilisateur derrière la charge utile (`ResolveurActeurInterface`).

> **⚠** L'étape 3 est celle qu'on supprime « parce que l'identifiant est déjà
> dans le jeton ». C'est elle qui refuse un compte supprimé ou désactivé depuis
> l'émission du jeton.

**Le résultat est mémorisé.** Plusieurs cas d'usage peuvent demander l'identité
dans la même requête ; le jeton n'est vérifié qu'une fois — un contrôle
d'auto-test le vérifie.

**Les deux formes d'en-tête sont acceptées** : `Bearer xxx` et `xxx` seul, le
préfixe étant insensible à la casse. Les deux circulent dans la nature.

**Ce qu'il lève**

| Situation | Type |
|---|---|
| en-tête absent ou vide | `JETON_ABSENT` |
| jeton vide après retrait du préfixe | `JETON_MALFORME` |
| signature, format, expiration | délégué à `JetonInterface` |
| le résolveur rend `null` | `ACTEUR_INTROUVABLE` |

---

## 5.3 `Presentateur\PresentateurAbstrait`

**Signature**

```php
abstract class PresentateurAbstrait
{
    abstract public function traduire(ErreurMetier $erreur): ReponseHttp;
    public function panne(): ReponseHttp;                                      // 500
    final protected function reponse(int $code, array $corps): ReponseHttp;
    protected function echec(int $code, string $statut, string $message): ReponseHttp;
}
```

**Rôle** — Traduire un résultat de domaine en réponse HTTP.

**C'est ici, et nulle part ailleurs, que vit le contrat de l'API.** Un
présentateur par endpoint, et le contrat se lit d'un coup d'œil au lieu d'être
dispersé dans les dépôts et les cas d'usage.

**La conséquence pratique compte autant que le principe** : un présentateur n'a
**aucune dépendance** — ni base, ni réseau, ni cas d'usage. Un test l'instancie
et vérifie le contrat entier en mémoire, sans serveur. C'est ainsi que la suite
d'équivalence du squelette couvre vingt réponses en quelques millisecondes.

**Règle de sûreté** — Un présentateur ne laisse jamais passer le message d'une
exception technique.

> **⚠** `SQLSTATE[HY000] [2002] Connection refused` livre au consommateur le
> moteur, l'hôte et parfois l'utilisateur de la base. **Traduisez, ne relayez
> pas.** Deux contrôles d'équivalence vérifient qu'aucun `SQLSTATE` n'apparaît
> dans un corps de réponse.

**`panne()` est redéfinissable, mais gardez le code 500.** Un incident technique
rendu en 200 fait croire au succès à tous les appelants qui ne lisent que le
statut.

**`echec()` propose un format par défaut** — `{"statut": …, "message": …}`. Si
votre API existante en rend un autre, n'utilisez pas ce raccourci et construisez
la réponse avec `reponse()`.

> **Le format existant prime toujours sur la convention de la bibliothèque.**
> Des tiers le lisent.

**Écrire son présentateur**

```php
final class PresentateurFacture extends PresentateurCommun
{
    public function facture(Facture $facture): ReponseHttp
    {
        return $this->reponse(200, [
            'statut'        => 'succes',
            'numero'        => $facture->numero(),
            'montant'       => $facture->montantAffiche(),
            'etat'          => $facture->statut(),
            'date_emission' => $facture->dateEmission(),
        ]);
    }

    public function traduire(ErreurMetier $erreur): ReponseHttp
    {
        switch ($erreur->type()) {
            case ErreurFacturation::FACTURE_INTROUVABLE:
                return $this->echec(404, 'erreur', 'facture introuvable');
            default:
                return parent::traduire($erreur);   // ← ne jamais oublier
        }
    }
}
```

Le `default` qui délègue au parent est ce qui évite de recopier dix traductions
dans chaque endpoint.

---

## 5.4 `Presentateur\PresentateurCommun`

**Signature**

```php
class PresentateurCommun extends PresentateurAbstrait
{
    public function traduire(ErreurMetier $erreur): ReponseHttp;
}
```

**Rôle** — Traductions des dix types définis par la bibliothèque.

**Pourquoi** — Tout endpoint authentifié rencontre les mêmes six erreurs de
jeton. Les redécrire dans chaque présentateur, c'est six occasions de répondre
401 ici et 403 là pour la même cause.

**La table de traduction par défaut**

| Type | Code | Message |
|---|---|---|
| `PARAMETRE_MANQUANT` | 400 | le détail, qui nomme les paramètres absents |
| `RESSOURCE_INTROUVABLE` | 404 | `ressource introuvable` |
| `JETON_ABSENT` | 400 | `token introuvable` |
| `JETON_MALFORME` | 401 | `token invalide` |
| `JETON_MAL_SIGNE` | 401 | `token invalide` |
| `JETON_EXPIRE` | 401 | `token expire` |
| `ACTEUR_INTROUVABLE` | 401 | `token invalide` |
| `IDENTIFIANTS_INVALIDES` | 403 | `identifiants invalides` |
| `ECHEC_ECRITURE` | 500 | `panne()` |
| `ECHEC_JOURNALISATION` | 500 | `panne()` |
| *(inconnu)* | 500 | `panne()` |

**Deux choix méritent d'être expliqués** :

**`JETON_MALFORME` et `JETON_MAL_SIGNE` rendent la même réponse, délibérément.**
Distinguer « illisible » de « mal signé » indique à qui forge des jetons lequel
de ses essais approche du but. Un contrôle d'équivalence compare les deux corps
JSON pour s'assurer qu'ils restent identiques.

**`JETON_EXPIRE` se distingue, lui.** Le client légitime doit savoir qu'il lui
suffit de se reconnecter — c'est une information utile, et elle n'aide personne
à forger quoi que ce soit.

**`PARAMETRE_MANQUANT` laisse passer le détail**, parce qu'il nomme les
paramètres absents : information utile à l'appelant, et qui ne révèle rien du
système. C'est la seule exception à la règle « ne relayez pas `getMessage()` »,
et elle tient au fait que ce message est composé par une fabrique nommée, pas
par une couche technique.

**Redéfinissez-la sans hésiter** si votre API existante répond autrement. Ces
codes sont un point de départ, pas une norme.

