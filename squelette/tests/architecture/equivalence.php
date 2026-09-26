<?php
declare(strict_types=1);

/**
 * Suite d'equivalence du squelette.
 *
 *     php tests/architecture/equivalence.php
 *
 * NI BASE, NI SERVEUR, NI RESEAU. Tout tourne en memoire, en une fraction de
 * seconde. C'est ce qui permet de la relancer apres chaque modification --
 * une suite qu'on ne relance pas ne protege de rien.
 *
 * Ce qu'elle verifie, et dans cet ordre :
 *   1. les regles du domaine ;
 *   2. les cas d'usage, cas nominal ET cas de refus ;
 *   3. LE CONTRAT HTTP -- codes, cles, ordre des cles ;
 *   4. le routage, y compris ce qui se passe quand tout va mal.
 *
 * Le point 3 est celui qui protege les consommateurs tiers. Un refactoring
 * peut tout reorganiser en dessous : tant que ces controles passent, aucun
 * client n'a besoin d'etre prevenu.
 */

require dirname(dirname(__DIR__)) . '/autoload.php';
require __DIR__ . '/doubles.php';

use App\Exemple\Application\Connecter;
use App\Exemple\Application\ConsulterFacture;
use App\Exemple\Domain\Entite\Facture;
use App\Exemple\Domain\ErreurFacturation;
use App\Exemple\Presentation\Presentateur\PresentateurConnexion;
use App\Exemple\Presentation\Presentateur\PresentateurFacture;
use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Http\Aiguillage;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Http\Routeur;
use PhpCleanCode\Presentation\Presentateur\PresentateurCommun;
use PhpCleanCode\Presentation\ReponseHttp;
use PhpCleanCode\Test\AuthentificationFactice;
use PhpCleanCode\Test\JetonFactice;
use PhpCleanCode\Test\JournalFactice;
use PhpCleanCode\Test\Verificateur;

$v = new Verificateur();

// ---------------------------------------------------------------------------
$v->section('1. Domaine');

$facture = new Facture('F-2026-001', 125050, Facture::EN_ATTENTE, '2026-09-01');
$v->egal('1250.50', $facture->montantAffiche(), 'centimes rendus en unites, deux decimales');
$v->vrai($facture->estPayable(), 'une facture en attente est payable');

$payee = new Facture('F-2026-002', 1000, Facture::PAYEE, '2026-09-02');
$v->egal(false, $payee->estPayable(), 'une facture payee ne l est plus');

// leve() ne guette que les ErreurMetier ; un invariant d'entite se defend
// avec une exception de PHP, qu'on attrape donc a la main.
$refuse = false;
try {
    new Facture('', 0, Facture::EN_ATTENTE, '2026-09-01');
} catch (InvalidArgumentException $e) {
    $refuse = true;
}
$v->vrai($refuse, 'un numero vide est refuse a la construction');

// ---------------------------------------------------------------------------
$v->section('2. Cas d usage');

$factures = new DepotFactureFactice();
$factures->ajouter($facture);

$consulter = new ConsulterFacture(AuthentificationFactice::acteur(7, 'test'), $factures);
$v->egal('F-2026-001', $consulter->executer('F-2026-001')->numero(), 'facture existante rendue');

$v->leve(
    ErreurFacturation::FACTURE_INTROUVABLE,
    function () use ($consulter) {
        return $consulter->executer('F-INCONNUE');
    },
    'facture absente : FACTURE_INTROUVABLE'
);

$v->leve(
    ErreurMetier::PARAMETRE_MANQUANT,
    function () use ($consulter) {
        return $consulter->executer('');
    },
    'numero vide : PARAMETRE_MANQUANT'
);

// L ORDRE COMPTE : l authentification doit passer AVANT la lecture, sinon un
// appelant anonyme apprend quelles factures existent selon la reponse.
$refuse  = AuthentificationFactice::refuse(ErreurMetier::JETON_EXPIRE);
$compteurAvant = $factures->appels;
$v->leve(
    ErreurMetier::JETON_EXPIRE,
    function () use ($refuse, $factures) {
        return (new ConsulterFacture($refuse, $factures))->executer('F-2026-001');
    },
    'jeton expire : le cas d usage refuse'
);
$v->egal($compteurAvant, $factures->appels, 'aucune lecture du depot sans authentification');

$utilisateurs = new DepotUtilisateurFactice();
$utilisateurs->ajouter(7, 'alice', 'motdepasse');
$journal   = new JournalFactice();
$connecter = new Connecter($utilisateurs, new JetonFactice(), $journal, 900);

$resultat = $connecter->executer('alice', 'motdepasse');
$v->egal(900, $resultat['expire_dans'], 'duree du jeton rendue');
$v->egal(1, $journal->compte(), 'la connexion est tracee');

$v->leve(
    ErreurMetier::IDENTIFIANTS_INVALIDES,
    function () use ($connecter) {
        return $connecter->executer('alice', 'faux');
    },
    'mot de passe faux : IDENTIFIANTS_INVALIDES'
);
$v->leve(
    ErreurMetier::IDENTIFIANTS_INVALIDES,
    function () use ($connecter) {
        return $connecter->executer('inconnu', 'motdepasse');
    },
    'compte inconnu : MEME erreur, pas d enumeration possible'
);

// Le journal rend false et ne leve pas : la connexion doit passer quand meme.
$journal->reussit = false;
$v->egal(
    900,
    $connecter->executer('alice', 'motdepasse')['expire_dans'],
    'journal en echec : la connexion aboutit tout de meme'
);
$journal->reussit = true;

// ---------------------------------------------------------------------------
$v->section('3. Contrat HTTP -- codes, cles et ordre des cles');

$pf = new PresentateurFacture();

$v->reponseEgale(200, [
    'statut'        => 'succes',
    'numero'        => 'F-2026-001',
    'montant'       => '1250.50',
    'etat'          => 'en_attente',
    'date_emission' => '2026-09-01',
], $pf->facture($facture), 'GET /facture succes');

$v->reponseEgale(404, [
    'statut'  => 'erreur',
    'message' => 'facture introuvable',
], $pf->traduire(ErreurFacturation::factureIntrouvable('F-X')), 'facture introuvable');

$v->reponseEgale(409, [
    'statut'  => 'erreur',
    'message' => 'facture deja payee',
], $pf->traduire(ErreurFacturation::factureDejaPayee('F-X')), 'facture deja payee');

// Le numero demande ne doit jamais etre renvoye dans le message.
$v->egal(
    false,
    strpos($pf->traduire(ErreurFacturation::factureIntrouvable('<script>'))->json(), 'script') !== false,
    'le numero fourni par l appelant n est pas renvoye'
);

// Types communs, herites de PresentateurCommun.
$v->egal(400, $pf->traduire(ErreurMetier::jetonAbsent())->code(), 'jeton absent : 400');
$v->egal(401, $pf->traduire(ErreurMetier::jetonExpire())->code(), 'jeton expire : 401');
$v->egal(500, $pf->traduire(ErreurMetier::echecEcriture('SQLSTATE[HY000]'))->code(), 'echec ecriture : 500');
$v->egal(
    false,
    strpos($pf->traduire(ErreurMetier::echecEcriture('SQLSTATE[HY000] Connection refused'))->json(), 'SQLSTATE') !== false,
    'le detail technique ne fuit PAS dans la reponse'
);

// Jeton illisible et jeton mal signe doivent etre indiscernables.
$v->egal(
    $pf->traduire(ErreurMetier::jetonMalforme())->json(),
    $pf->traduire(ErreurMetier::jetonMalSigne())->json(),
    'jeton malforme et mal signe : reponses identiques'
);

$pc = new PresentateurConnexion();
$v->reponseEgale(200, [
    'statut'      => 'succes',
    'token'       => 'abc',
    'expire_dans' => 900,
], $pc->jetonEmis('abc', 900), 'POST /login succes');

$v->reponseEgale(403, [
    'statut'  => 'erreur',
    'message' => 'identifiants invalides',
], $pc->traduire(ErreurMetier::identifiantsInvalides()), 'identifiants invalides');

// ---------------------------------------------------------------------------
$v->section('4. Routage');

$routeur = new Routeur('/exemple');
$routeur->ajouter(
    'facture',
    function () use ($consulter, $pf) {
        return function (Requete $r) use ($consulter, $pf) {
            return $pf->facture($consulter->executer((string) $r->parametre('numero', '')));
        };
    },
    function () {
        return new PresentateurFacture();
    },
    ['GET']
);

$aiguillage = new Aiguillage($routeur, new PresentateurCommun());

$reponse = $aiguillage->servir(new Requete('GET', '/exemple/facture', [], ['numero' => 'F-2026-001']));
$v->egal(200, $reponse->code(), 'route trouvee, prefixe d installation retire');

$v->egal(200, $aiguillage->servir(new Requete('GET', '/exemple/Facture/', [], ['numero' => 'F-2026-001']))->code(),
    'casse et barre finale ignorees');

$v->egal(404, $aiguillage->servir(new Requete('GET', '/exemple/inconnu'))->code(), 'chemin inconnu : 404');
$reponse405 = $aiguillage->servir(new Requete('POST', '/exemple/facture'));
$v->egal(405, $reponse405->code(), 'methode refusee : 405');
$v->egal('GET', $reponse405->entetes()['Allow'], '405 annonce les methodes permises');

// Un prefixe ne doit etre retire que lorsqu il forme un segment complet.
$routeurPrefixe = new Routeur('/api');
$routeurPrefixe->ajouter('x/facture', function () {}, function () { return new PresentateurCommun(); });
$v->vrai($routeurPrefixe->resoudre('/api/x/facture') !== null, 'prefixe exact retire');
$v->egal(null, $routeurPrefixe->resoudre('/apix/facture'), 'prefixe partiel conserve');

// Le presentateur de la route garde la main sur le format de la reponse 405.
$presentateur405 = new class extends PresentateurCommun {
    public function methodeNonAutorisee(array $methodes): ReponseHttp
    {
        return new ReponseHttp(405, ['erreur' => 'methode incorrecte'], [
            'Allow' => implode(', ', $methodes),
        ]);
    }
};
$routeur405 = new Routeur();
$routeur405->ajouter('format-405', function () {}, function () use ($presentateur405) {
    return $presentateur405;
}, ['GET']);
$reponse405Personnalisee = (new Aiguillage($routeur405, new PresentateurCommun()))
    ->servir(new Requete('POST', '/format-405'));
$v->egal(['erreur' => 'methode incorrecte'], $reponse405Personnalisee->corps(), '405 traduit par le presentateur de la route');
$v->egal('GET', $reponse405Personnalisee->entetes()['Allow'], '405 personnalisee conserve Allow');

$v->egal(404, $aiguillage->servir(new Requete('GET', '/exemple/facture', [], ['numero' => 'F-X']))->code(),
    'erreur metier rendue par le presentateur de la route');

// UNE PANNE PENDANT LE CABLAGE doit etre rendue par le presentateur de la
// route appelee, et surtout pas laisser fuiter l exception.
$casse = new Routeur();
$casse->ajouter(
    'casse',
    function () {
        throw new RuntimeException('base injoignable');
    },
    function () {
        return new PresentateurFacture();
    }
);
$incidents = 0;
$aiguillageCasse = new Aiguillage($casse, new PresentateurCommun(), function () use (&$incidents) {
    $incidents++;
});
$reponsePanne = $aiguillageCasse->servir(new Requete('GET', '/casse'));
$v->egal(500, $reponsePanne->code(), 'cablage en echec : 500, pas d exception qui remonte');
$v->egal(1, $incidents, 'l incident est journalise');
$v->egal(
    false,
    strpos($reponsePanne->json(), 'injoignable') !== false,
    'le message technique ne fuit PAS'
);

exit($v->bilan());
