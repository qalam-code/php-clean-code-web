<h1>Bonjour, <?= $this->echapper($nomAffiche) ?> !</h1>
<?php if ($erreur !== null): ?>
    <p role="alert"><?= $this->echapper($erreur) ?></p>
<?php endif; ?>
<form method="post" action="/bonjour">
    <label for="nom">Votre nom</label>
    <input id="nom" name="nom" type="text" maxlength="100" required value="<?= $this->echapper($nom) ?>">
    <button type="submit">Dire bonjour</button>
</form>