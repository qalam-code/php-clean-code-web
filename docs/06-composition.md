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
    final protected function definirService(string $identifiant, callable $fabrique, bool $partagee = true);
    final protected function resoudreService(string $identifiant);                // mixed
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

## 6.5 `Conteneur` : résolution explicite

Le conteneur ne devine pas les dépendances par réflexion. La fabrique lui
enregistre des fabriques concrètes, identifiées par une clé, puis demande leur
résolution quand une route est appelée. Par défaut, le service est partagé
pendant la durée de vie de cette fabrique (une requête HTTP). Passez `false`
au troisième argument de `definirService()` pour obtenir une nouvelle instance
à chaque résolution.

```php
$this->definirService(ControleurFacture::class, function () {
    return new ControleurFacture($this->consulterFacture(), new PresentateurFacture());
});
```

Les fabriques peuvent recevoir le conteneur si elles doivent résoudre une
dépendance, mais cela reste réservé à la composition. Un contrôleur ou un cas
d'usage qui reçoit le conteneur pourrait choisir lui-même ses dépendances au
moment de l'exécution : il dépendrait alors d'un *service locator*. À la place,
on lui passe directement les objets dont il a besoin dans son constructeur.

Les identifiants dupliqués, les services inconnus et les dépendances circulaires
sont signalés par des exceptions explicites. Il n'y a ni auto-wiring ni
instanciation par réflexion : chaque lien reste visible dans `Fabrique.php`.

## 6.6 `connexion()` : paresseuse deux fois

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

## 6.7 `surveillance()` : r�gl�e sur la configuration du serveur

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

## 6.8 �crire sa fabrique

Le squelette en donne un exemple complet. La structure :

```php
final class Fabrique extends FabriqueBase
{
    // 1. Les definitions viennent de routes/api.php ; les fabriques concretes restent ici.
    public function routeur(): Routeur
    {
        $routeur = new Routeur((string) (getenv('BASE_URI') ?: ''));

        $this->definirService(ControleurFacture::class, function () {
            return new ControleurFacture($this->consulterFacture(), new PresentateurFacture());
        });
        $this->definirService(PresentateurFacture::class, function () {
            return new PresentateurFacture();
        });

        foreach (require dirname(__DIR__) . '/routes/api.php' as $definition) {
            $prefixe = ucfirst($definition['service']);
            $idControleur = __NAMESPACE__ . '\\Presentation\\Controleur\\Controleur' . $prefixe;
            $idPresentateur = __NAMESPACE__ . '\\Presentation\\Presentateur\\Presentateur' . $prefixe;

            $routeur->ajouter(
                $definition['chemin'],
                function () use ($idControleur) {
                    return $this->resoudreService($idControleur);
                },
                function () use ($idPresentateur) {
                    return $this->resoudreService($idPresentateur);
                },
                $definition['methodes']
            );
        }

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

## 6.9 La configuration

Le squelette fournit un fichier .env.example. Composer le copie vers .env lors de la creation du projet. Le fichier .env est ignore par Git ; il contient les valeurs propres a chaque environnement.

    BASE_URI=
    DB_HOTE=127.0.0.1
    DB_BASE=exemple
    DB_UTILISATEUR=root
    DB_MOT_DE_PASSE=
    JWT_SECRET=

Le chargeur du squelette lit ce fichier sans dependance externe. Une variable deja fournie par Apache, le vhost ou le systeme reste prioritaire sur .env. En production, definissez les secrets directement dans l'environnement du serveur.

JWT_SECRET reste volontairement vide dans le modele. Generez une cle aleatoire et renseignez-la avant d'utiliser l'authentification. Sans cette cle, le squelette refuse de construire le service de jetons ; aucun secret de secours n'est utilise.
