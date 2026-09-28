<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->echapper($titre) ?></title>
    <?php foreach ($actifsCss as $actifCss): ?>
        <link rel="stylesheet" href="<?= $this->echapper($actifCss) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= $this->echapper($classeVue) ?>">
    <?= $this->rendre('partiels/entete') ?>
    <main>
        <?= $contenu ?>
    </main>
    <?= $this->rendre('partiels/pied-de-page') ?>
    <?php foreach ($actifsJs as $actifJs): ?>
        <script src="<?= $this->echapper($actifJs) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>