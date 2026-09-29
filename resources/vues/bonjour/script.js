(function () {
    var formulaire = document.querySelector('.vue-bonjour form');
    if (!formulaire) {
        return;
    }

    formulaire.addEventListener('submit', function () {
        var champNom = formulaire.querySelector('[name="nom"]');
        if (champNom) {
            champNom.value = champNom.value.trim();
        }
    });
}());