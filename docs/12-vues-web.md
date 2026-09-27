# Préparer une version avec vues web

Cette note fixe les frontières à respecter quand phpCleanCode évoluera de
l'API JSON vers des applications HTML. Elle ne modifie pas le contrat de la
version API.

## Ce que la version API fournit aujourd'hui

`ReponseHttp` transporte un corps sous forme de tableau et l'envoie toujours
en JSON. `Aiguillage::servir()` retourne ce type précis. Ces deux choix sont
cohérents pour l'API, mais ne représentent pas le corps HTML d'une page.

Il ne faut donc pas glisser du HTML dans un tableau JSON ni faire dépendre les
cas d'usage d'un moteur de gabarits. La couche Domaine et la couche Application
doivent rester indépendantes des vues.

## Évolution recommandée

1. Garder le contrat JSON existant et ses présentateurs dédiés.
2. Ajouter à la couche Présentation une réponse HTTP capable d'envoyer une
   chaîne avec son type de contenu, par exemple `text/html; charset=utf-8`.
3. Définir une frontière commune pour les réponses afin que l'aiguillage puisse
   transporter aussi bien une réponse JSON qu'une réponse HTML. Ce changement
   touche la signature publique de `Aiguillage::servir()` : le décider dans la
   version web, le couvrir par les suites API existantes et documenter sa
   compatibilité avant publication.
4. Ajouter un contrat de rendu de vue dans la couche Présentation. Un moteur
   de gabarits concret reste un adaptateur remplaçable ; les contrôleurs
   transmettent au présentateur les données nécessaires à la vue.
5. Garder les gabarits hors de `public/`, échapper les valeurs HTML par défaut
   et fournir une échappatoire explicite pour le contenu sûr.

## Fonctions web à décider séparément

Avant de les ajouter, préciser le besoin pour les formulaires et validations,
les sessions et cookies, la protection CSRF, les redirections, les fichiers
statiques et la négociation entre HTML et JSON. Elles ne sont pas nécessaires
à l'API JSON actuelle et ne doivent pas être ajoutées implicitement à son
contrat.

## Compatibilité

Le socle vise PHP 7.0. Les nouvelles réponses, interfaces et outils de vue
devront respecter ce minimum tant qu'il reste annoncé. Le moteur de gabarits
devra être une dépendance optionnelle du squelette web, pas une dépendance
imposée aux projets qui n'utilisent que l'API.
