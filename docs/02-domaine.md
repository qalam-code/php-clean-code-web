# 2. Le domaine et les ports de l'application

Le domaine porte les concepts et contrats m�tier. Les interfaces requises par
les cas d'usage sont des ports dans `src/Application/Port/` : elles restent
dans la couche int�rieure, mais ne sont pas des concepts du domaine.

```
Domain/
+-- ErreurMetier.php
+-- Entite/
�   +-- Identite.php
+-- Contrat/
    +-- DepotInterface.php

Application/Port/
+-- AuthentificationInterface.php
+-- JetonInterface.php
+-- JournalInterface.php
+-- ResolveurActeurInterface.php
+-- SurveillanceInterface.php
```

---

## 2.1 `ErreurMetier`

**Signature**

```php
class ErreurMetier extends Exception
{
    const PARAMETRE_MANQUANT     = 'parametre_manquant';
    const RESSOURCE_INTROUVABLE  = 'ressource_introuvable';
    const ECHEC_ECRITURE         = 'echec_ecriture';
    const IDENTIFIANTS_INVALIDES = 'identifiants_invalides';
    const JETON_ABSENT           = 'jeton_absent';
    const JETON_MALFORME         = 'jeton_malforme';
    const JETON_MAL_SIGNE        = 'jeton_mal_signe';
    const JETON_EXPIRE           = 'jeton_expire';
    const ACTEUR_INTROUVABLE     = 'acteur_introuvable';
    const ECHEC_JOURNALISATION   = 'echec_journalisation';

    public function __construct(string $type, string $detail = '');
    public function type(): string;
    public function estDeType(string $type): bool;

    public static function parametreManquant(string $parametres): self;
    public static function ressourceIntrouvable(string $quoi): self;
    public static function echecEcriture(string $detail): self;
    public static function identifiantsInvalides(): self;
    public static function jetonAbsent(): self;
    public static function jetonMalforme(): self;
    public static function jetonMalSigne(): self;
    public static function jetonExpire(): self;
    public static function acteurIntrouvable(): self;
    public static function echecJournalisation(): self;
}
```

**R�le** � Une seule exception pour tout le domaine, qualifi�e par un *type*.

**Pourquoi** � Le domaine dit ce qui ne va pas ; il ne dit pas quel code HTTP
r�pondre. C'est le pr�sentateur, tout en haut, qui traduit un type en code et
en message. Trois cons�quences :

1. Changer un libell� ne touche jamais le domaine.
2. Un m�me type peut se traduire diff�remment selon l'endpoint.
3. Une classe d'exception par cas produirait vingt classes vides et autant de
   `catch` � tenir � jour. Un type suffit.

**�tendre** � Votre projet ajoute ses types dans une sous-classe :

```php
final class ErreurFacturation extends ErreurMetier
{
    const FACTURE_DEJA_PAYEE = 'facture_deja_payee';

    public static function factureDejaPayee(string $numero): self
    {
        return new self(self::FACTURE_DEJA_PAYEE, 'facture ' . $numero);
    }
}
```

Les dix types de base restent disponibles, et `PresentateurCommun` sait d�j� les
traduire : vous n'�crivez que ce qui est propre � votre m�tier.

**Les fabriques nomm�es** � `ErreurMetier::jetonExpire()` plut�t que
`new ErreurMetier('jeton_expire', '�')`. Elles seules garantissent qu'un type
connu va avec le bon message, et elles se retrouvent d'un coup d'�il dans l'IDE.

> **? Le constructeur est public � par obligation, pas par intention.**
>
> Il devrait �tre priv� : seules les fabriques devraient construire une erreur.
> PHP l'interdit. Une m�thode qui en red�finit une autre ne peut pas r�duire sa
> visibilit�, et `Exception::__construct()` est publique. Le d�clarer priv� fait
> �chouer **la d�claration de la classe enti�re** :
>
> ```
> Fatal error: Access level to ErreurMetier::__construct()
> must be public (as in class Exception)
> ```
>
> PHP 8 a rel�ch� la r�gle pour les constructeurs. Ne vous y fiez pas : le code
> doit se d�clarer sous la version la plus ancienne que vous visez. Voir
> [`11-pieges.md`](11-pieges.md), pi�ge n� 1 � celui-ci a mis une API enti�re
> par terre.

**Le `detail` n'est pas un message d'API.** `getMessage()` contient le d�tail
technique (`SQLSTATE[HY000] Connection refused`, un num�ro de facture, un nom de
verrou). Il va au journal. Le pr�sentateur d�cide, cas par cas, ce qui remonte
au consommateur � le plus souvent : rien de tout �a.

---

## 2.2 `Entite\Identite`

**Signature**

```php
final class Identite
{
    public function __construct(int $id, string $nom);
    public function id(): int;
    public function nom(): string;
}
```

**R�le** � Le consommateur de l'API, une fois authentifi�.

**Pourquoi** � C'est la **seule source d'identit� du syst�me**. Aucune m�thode
ne construit une `Identite` depuis une requ�te : elle ne peut venir que d'un
jeton v�rifi� (`Authentificateur`) ou d'une authentification r�ussie
(`DepotUtilisateur::parIdentifiants`).

> **?** Cette r�gle n'est pas th�orique. Accepter un identifiant d'utilisateur
> transmis dans le corps d'une requ�te � et court-circuiter le jeton quand il est
> pr�sent � est une faille classique : n'importe quel appelant authentifi� agit
> alors au nom de n'importe qui. Sur un syst�me de paiement, cela revient �
> imputer l'argent � la mauvaise caisse.

**Volontairement minimale.** Ni r�le, ni permissions, ni adresse. Ce dont un cas
d'usage a besoin pour tracer et imputer une action tient en deux champs. Si
votre projet a besoin de r�les, ajoutez-les dans **votre** entit� utilisateur,
c�t� projet � pas ici.

---

## 2.3 `Application\Port\JetonInterface`

**Signature**

```php
interface JetonInterface
{
    public function emettre(array $charge, int $dureeSecondes): string;
    public function verifier(string $jeton): array;   // @throws ErreurMetier
}
```

**R�le** � �mettre et v�rifier le jeton d'acc�s.

**Pourquoi** � Le domaine ne sait pas ce qu'est un JWT. Il sait qu'il existe un
moyen de transformer une identit� en cha�ne, et cette cha�ne en identit�. La
biblioth�que en fournit une impl�mentation HS256 (`JetonJwt`), mais rien
n'oblige � l'utiliser : jeton opaque en base, PASETO, session � seul ce contrat
est connu du reste du code.

**Ce que `verifier()` l�ve** � `JETON_MALFORME`, `JETON_MAL_SIGNE`,
`JETON_EXPIRE`. Elle ne retourne jamais `false` : une v�rification qui �choue
est une erreur, pas une valeur.

---

## 2.4 `Application\Port\AuthentificationInterface`

**Signature**

```php
interface AuthentificationInterface
{
    public function identifier(): Identite;   // @throws ErreurMetier
}
```

**R�le** � R�pondre � � qui appelle ? �.

**Pourquoi** � Un cas d'usage qui a besoin de savoir � qui imputer une action
d�pend de cette interface, et de rien d'autre. Il ignore s'il y a un jeton, un
en-t�te, un cookie ou une session derri�re.

> **? Impl�mentez-la paresseusement.** La racine de composition c�ble
> l'authentificateur dans tous les cas d'usage. Si sa construction ouvre la
> connexion � la base, une base injoignable fait �chouer l'endpoint *avant*
> qu'il ait pu r�pondre � jeton absent � � une requ�te qui n'en portait pas. Le
> diagnostic rendu au consommateur devient faux, et le d�faut ne se voit que le
> jour o� la base tombe. D'o� `AuthentificationDifferee` � voir
> [`03-infrastructure.md`](03-infrastructure.md) � 3.6.

**Ce qu'elle l�ve** � `JETON_ABSENT`, `JETON_MALFORME`, `JETON_MAL_SIGNE`,
`JETON_EXPIRE`, `ACTEUR_INTROUVABLE`.

---

## 2.5 `Application\Port\JournalInterface`

**Signature**

```php
interface JournalInterface
{
    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool;
}
```

**R�le** � Tracer une action.

**Pourquoi un bool�en, et pas une exception** � C'est la d�cision de conception
la plus importante de ce fichier.

Un journal qui l�ve une exception fait �chouer l'op�ration qu'il devait
seulement accompagner : la transaction est pass�e, l'argent a boug�, et le
consommateur re�oit une erreur parce que l'�criture du journal a �chou�.

Retourner `false` laisse l'appelant d�cider. Un cas d'usage o� la trace est une
obligation r�glementaire traduira `false` en erreur ; ailleurs, il l'ignorera.
Dans les deux cas **le choix est visible dans le cas d'usage**, et non enfoui
dans une impl�mentation.

```php
// Le choix, �crit noir sur blanc :
// refuser une connexion valide parce que sa trace n'a pas pu s'�crire
// serait pire que la trace perdue.
$this->journal->enregistrer($identite->id(), 'connexion');
```

> **?** La contrepartie doit �tre assum�e : si personne ne lit ce bool�en, une
> table de journal pleine ou verrouill�e se traduit par une **perte de traces
> totalement silencieuse**.

---

## 2.6 `Application\Port\SurveillanceInterface`

**Signature**

```php
interface SurveillanceInterface
{
    public function tempsRestant(): bool;
    public function ecoule(): float;
}
```

**R�le** � Garde-temps d'un traitement.

**Pourquoi** � Utile d�s qu'un cas d'usage appelle un service tiers ou boucle
sur un ensemble de taille inconnue. Il consulte la surveillance entre deux
�tapes et s'arr�te proprement, au lieu de se faire tuer par
`max_execution_time` **au milieu d'une �criture**.

```php
foreach ($factures as $facture) {
    if (!$this->surveillance->tempsRestant()) {
        break;   // arr�t propre, �tat coh�rent
    }
    $this->payer($facture);
}
```

**`tempsRestant()` ne r�pond pas � reste-t-il du temps ? �** mais � reste-t-il
au moins une �tape de temps ? �. La diff�rence est la marge � voir
[`03-infrastructure.md`](03-infrastructure.md) � 3.5.

---

## 2.7 `Application\Port\ResolveurActeurInterface`

**Signature**

```php
interface ResolveurActeurInterface
{
    public function resoudre(array $charge);   // null = plus personne
}
```

**R�le** � Passer du contenu d'un jeton � un utilisateur r�el.

**Pourquoi c'est une �tape s�par�e de la v�rification du jeton** � Un jeton peut
�tre parfaitement sign� et non expir�, et pourtant d�signer un compte supprim�
ou d�sactiv� depuis son �mission. Sans cette �tape, le syst�me fait confiance �
une photographie vieille de plusieurs heures.

> **?** L'impl�mentation na�ve � prendre l'identifiant �crit dans la charge et
> s'en contenter � fait dispara�tre ce contr�le, sans que rien ne le signale.
> **Allez chercher l'utilisateur.**

---

## 2.8 `Contrat\DepotInterface`

**Signature**

```php
interface DepotInterface
{
    // volontairement vide
}
```

**R�le** � Marqueur. N'impose aucune m�thode.

**Pourquoi vide** � Un d�p�t se d�crit par le besoin du domaine, pas par une API
g�n�rique :

```php
interface DepotFactureInterface extends DepotInterface
{
    public function trouverParNumero(string $numero);
    public function impayeesDe(int $abonneId): array;
}
```

Une interface commune � `find` / `save` / `delete` ferait fuiter la base de
donn�es dans le domaine : le cas d'usage se mettrait � raisonner en lignes et en
cl�s primaires au lieu de factures et d'abonn�s, et toute optimisation SQL
deviendrait impossible sans changer le contrat.

**Le vocabulaire est le test.** `impayeesDe($abonne)`, pas `select($where)`. Si
les m�thodes de vos d�p�ts ressemblent � du SQL traduit, la base a remont� dans
le domaine.

Ce marqueur ne sert donc qu'� une chose : rendre les d�p�ts rep�rables, pour un
contr�le d'architecture ou un scan d'autochargement.

