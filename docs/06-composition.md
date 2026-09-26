# 6. La racine de composition

`src/Fabrique.php` � **un fichier**, et probablement le plus important du socle.

---

## 6.1 `Fabrique`

**Signature**

```php
abstract class Fabrique
{
    public function __construct(Requete $requete);

    abstract public function routeur(): Routeur;
    abstract protected function decrireConnexion(): FabriqueConnexion;

    final public function requete(): Requete;
    final public function connexion(): FabriqueConnexion;
    public function surveillance(): SurveillanceInterface;

    final protected function partage(string $cle, callable $construire);          // mixed
    final protected function authentificationDifferee(callable $construire): AuthentificationInterface;
}
```

**R�le** � L'endroit o� les impl�mentations concr�tes sont assembl�es et reli�es aux contrats de l'application. Les objets simples de valeur peuvent �tre construits ailleurs ; les d�pendances d'infrastructure restent c�bl�es � cette fronti�re.

---

## 6.2 Pourquoi concentrer toutes les instanciations

Dans les cas d'usage et le domaine, une classe **re�oit** ses d�pendances et ne fabrique pas ses adaptateurs d'infrastructure.
Cette discipline a un co�t d'�criture � cette classe grossit � et un b�n�fice
qu'aucune autre technique ne donne : tout le reste du code devient testable sans
base, sans r�seau et sans serveur, parce qu'il suffit de lui passer autre chose.

**Lisez cette classe comme le plan du syst�me.** Qui d�pend de qui y est �crit
noir sur blanc, en un seul fichier. Sur un projet repris, c'est le premier
fichier � ouvrir : il dit quels endpoints existent, quel cas d'usage r�pond �
chacun, et de quoi ce cas d'usage d�pend.

---

## 6.3 Deux r�gles � ne pas enfreindre

**1. Rien ici n'ouvre de connexion ni n'appelle le r�seau.** On assemble des
objets, on ne travaille pas. `FabriqueConnexion` et `AuthentificationDifferee`
existent pour cela.

> **?** Le jour o� la fabrique ouvre la base pour se construire, un endpoint qui
> devait r�pondre � param�tre manquant � r�pond � erreur interne � d�s que la
> base est indisponible.

**2. Aucun cas d'usage n'instancie d'infrastructure.** Le jour o� un d�p�t fait
`new PDO` dans un coin, la racine de composition ment � et le test qui croyait
travailler en m�moire attaque la base de production.

Ce point se v�rifie m�caniquement :

```bash
grep -rn "new PDO\|new .*Pdo(" src/ --include="*.php" | grep -v Fabrique
```

---

## 6.4 `partage()` : m�mo�sation

```php
private function utilisateurs(): DepotUtilisateurInterface
{
    return $this->partage('utilisateurs', function () {
        return new DepotUtilisateurPdo($this->connexion());
    });
}
```

Deux cas d'usage c�bl�s dans la m�me requ�te partagent alors le m�me d�p�t, donc
la m�me connexion � et non deux.

**La cl� est une cha�ne libre**, mais elle doit �tre unique : deux services
partageant la m�me cl� se substituent silencieusement l'un � l'autre. Prenez le
nom de la m�thode.

**`array_key_exists` plut�t que `isset`** : un service qui vaudrait l�gitimement
`null` serait sinon reconstruit � chaque appel.

---

## 6.5 `connexion()` : paresseuse deux fois

```php
final public function connexion(): FabriqueConnexion
{
    if ($this->connexion === null) {
        $this->connexion = $this->decrireConnexion();
    }
    return $this->connexion;
}
```

Deux niveaux de paresse se superposent, et c'est voulu :

1. `decrireConnexion()` n'est appel�e qu'au premier `connexion()` � l'objet
   d�crivant la connexion n'est m�me pas construit tant qu'aucun d�p�t n'est
   c�bl� ;
2. `FabriqueConnexion::pdo()` n'ouvre la socket qu'� la premi�re requ�te SQL
   r�ellement ex�cut�e.

R�sultat : un endpoint qui refuse une requ�te malform�e ne touche jamais la
base.

---

## 6.6 `surveillance()` : r�gl�e sur la configuration du serveur

```php
public function surveillance(): SurveillanceInterface
{
    return $this->partage('surveillance', function () {
        $limite = (float) ini_get('max_execution_time');
        return new SurveillanceTimeout($limite > 0 ? $limite : 30.0, 5.0);
    });
}
```

`max_execution_time` vaut `0` en CLI (pas de limite) : le repli � 30 secondes
�vite qu'un script en ligne de commande croie avoir un budget infini alors que
le m�me code tournera sous Apache avec une limite r�elle.

Red�finissez cette m�thode si la marge de cinq secondes ne convient pas � votre
�tape la plus longue.

---

## 6.7 �crire sa fabrique

Le squelette en donne un exemple complet. La structure :

```php
final class Fabrique extends FabriqueBase
{
    // 1. Les routes � la carte de l'application
    public function routeur(): Routeur
    {
        $routeur = new Routeur((string) (getenv('BASE_URI') ?: ''));

        $routeur->ajouter(
            'facture',
            function () { return new ControleurFacture($this->consulterFacture(), new PresentateurFacture()); },
            function () { return new PresentateurFacture(); },
            ['GET', 'POST']
        );

        return $routeur;
    }

    // 2. La connexion � d�crite, pas ouverte
    protected function decrireConnexion(): FabriqueConnexion
    {
        $base = (string) (getenv('DB_BASE') ?: 'exemple');
        return new FabriqueConnexion(
            'mysql:host=' . (getenv('DB_HOTE') ?: '127.0.0.1') . ';dbname=' . $base . ';charset=utf8mb4',
            (string) getenv('DB_UTILISATEUR'),
            (string) getenv('DB_MOT_DE_PASSE'),
            $base                                  // ? le USE explicite
        );
    }

    // 3. Les cas d'usage � non m�mo�s�s : un par requ�te suffit
    private function consulterFacture(): ConsulterFacture
    {
        return new ConsulterFacture($this->authentification(), $this->factures());
    }

    // 4. Les services � m�mo�s�s
    private function factures(): DepotFactureInterface
    {
        return $this->partage('factures', function () {
            return new DepotFacturePdo($this->connexion());
        });
    }
}
```

**Cas d'usage non m�mo�s�s, services m�mo�s�s.** Un cas d'usage est bon march� et
sans �tat ; un d�p�t porte la connexion, il doit �tre unique.

---

## 6.8 La configuration

**Elle vient de l'environnement**, pas d'un fichier versionn� :

```apache
SetEnv JWT_SECRET        une-valeur-aleatoire-longue
SetEnv DB_HOTE           127.0.0.1
SetEnv DB_BASE           exemple
SetEnv DB_UTILISATEUR    exemple
SetEnv DB_MOT_DE_PASSE   ...
SetEnv BASE_URI          /exemple
```

> **? Pas de secret par d�faut.** Le squelette l�ve une exception si
> `JWT_SECRET` est absent :
>
> ```php
> if ($secret === '') {
>     throw new \RuntimeException('JWT_SECRET absent de la configuration');
> }
> ```
>
> Une valeur de repli � `secret`, `changeme`, `SECRET` � finit toujours en
> production, et alors n'importe qui peut forger un jeton valide pour n'importe
> quel compte. **Mieux vaut refuser de d�marrer.**

Pour les autres r�glages (h�te, nom de base), un repli est acceptable : une
mauvaise valeur se manifeste imm�diatement par une erreur de connexion, pas par
une faille silencieuse.

