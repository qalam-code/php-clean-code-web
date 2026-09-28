<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->echapper($titre) ?></title>
</head>
<body>
    <?= $this->rendre('partiels/entete') ?>
    <main>
        <?= $contenu ?>
    </main>
    <?= $this->rendre('partiels/pied-de-page') ?>
</body>
</html>