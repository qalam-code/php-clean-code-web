# Creer un projet avec Composer

Le squelette du framework est distribue dans un depot GitHub distinct. Avant la premiere creation de projet sur une machine, enregistrez ce depot VCS dans la configuration globale de Composer :

```powershell
composer config --global repositories.qalam-skeleton vcs https://github.com/qalam-code/php-clean-code-skeleton
```

Vous pouvez ensuite creer une application a partir du squelette :

```powershell
composer create-project qalam-code/php-clean-code-skeleton mon-projet
```

Remplacez `mon-projet` par le nom du dossier souhaite. L'enregistrement global du depot est a faire une seule fois par environnement Composer.

Pour retirer cet enregistrement plus tard :

```powershell
composer config --global --unset repositories.qalam-skeleton
```
