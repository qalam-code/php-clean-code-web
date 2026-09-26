<?php
/**
 * Cas de caracterisation -- EXEMPLE COMPLET, TIRE D'UN PROJET REEL.
 *
 * Copiez ce fichier en cases.php et REMPLACEZ INTEGRALEMENT son contenu par
 * les endpoints de votre projet. Il est livre tel quel parce qu'un exemple qui
 * a reellement servi apprend plus qu'un squelette vide : on y voit comment se
 * decrivent les cas d'authentification degradee, comment se declare un cas
 * mutant, et pourquoi certaines reponses se comparent en structure.
 *
 * Un cas ne decrit PAS le comportement souhaitable : il decrit le
 * comportement actuel, celui sur lequel les consommateurs tiers se sont
 * alignes. La reference est enregistree depuis le serveur, jamais ecrite a
 * la main. Une divergence apres refactoring est une rupture de contrat.
 *
 * Champs :
 *   name      identifiant stable, sert de nom de fichier de reference
 *   groupe    regroupement d'affichage
 *   risque    'sur'     lecture seule, rejouable a volonte
 *             'mutant'  modifie la base, consomme sa fixture
 *             'externe' appelle un fournisseur tiers et credite un compteur reel
 *   auth      'aucune' | 'valide' | 'absente' | 'malformee' | 'signature' | 'expiree'
 *   comparer  'complet'   la valeur entiere est comparee (reponses deterministes)
 *             'structure' seule la forme est comparee (contenu dependant de la base)
 *   masquer   cles dont la valeur varie a chaque appel
 *   requiert  fixtures obligatoires ; si l'une est vide, le cas est ignore
 *
 * Compatible PHP 7.0 strict.
 *
 * @param array $config
 * @return array
 */
/**
 * Lecture tolerante d'une fixture.
 *
 * Une fixture absente de config.php vaut chaine vide : le cas qui en depend
 * sera signale IGNORE, au lieu de faire echouer le chargement de tout le
 * fichier. C'est ce qui permet de demarrer avec deux ou trois fixtures
 * renseignees et de completer au fur et a mesure.
 *
 * @param array  $f fixtures
 * @param string $cle
 * @return mixed
 */
function carac_fx(array $f, $cle)
{
    return isset($f[$cle]) ? $f[$cle] : '';
}

function caracterisation_cases(array $config)
{
    $f = $config['fixtures'];

    $cases = array();

    // =====================================================================
    // POST /login
    // =====================================================================
    $cases[] = array(
        'name'     => 'login_succes',
        'groupe'   => 'login',
        'risque'   => 'sur',
        'auth'     => 'aucune',
        'method'   => 'POST',
        'path'     => '/login',
        'body'     => array('username' => $config['auth']['username'], 'pwd' => $config['auth']['pwd']),
        'comparer' => 'complet',
        'masquer'  => array('token'),
        'requiert' => array(),
        'note'     => 'Objet {token}, sans champ statut. Forme propre a cet endpoint.',
    );

    $cases[] = array(
        'name'     => 'login_identifiants_invalides',
        'groupe'   => 'login',
        'risque'   => 'sur',
        'auth'     => 'aucune',
        'method'   => 'POST',
        'path'     => '/login',
        'body'     => array('username' => $config['auth']['username'], 'pwd' => 'mot-de-passe-volontairement-faux'),
        'comparer' => 'complet',
        'masquer'  => array(),
        'requiert' => array(),
        'note'     => 'Attendu 403 {message: identifiants invalides}.',
    );

    $cases[] = array(
        'name'     => 'login_parametre_manquant',
        'groupe'   => 'login',
        'risque'   => 'sur',
        'auth'     => 'aucune',
        'method'   => 'POST',
        'path'     => '/login',
        'body'     => array('username' => $config['auth']['username']),
        'comparer' => 'complet',
        'masquer'  => array(),
        'requiert' => array(),
        'note'     => 'pwd absent. Attendu 400 {message: parametre manquant}.',
    );

    $cases[] = array(
        'name'     => 'login_corps_vide',
        'groupe'   => 'login',
        'risque'   => 'sur',
        'auth'     => 'aucune',
        'method'   => 'POST',
        'path'     => '/login',
        'body'     => array(),
        'comparer' => 'complet',
        'masquer'  => array(),
        'requiert' => array(),
        'note'     => 'Corps JSON vide.',
    );

    // =====================================================================
    // Authentification transverse, eprouvee sur /get-factures
    // =====================================================================
    $authCases = array(
        array('absente',   'auth_token_absent',       'Aucun en-tete Authorization. Attendu 400 {message: token introuvable}.'),
        array('malformee', 'auth_token_malforme',     'Jeton ne respectant pas le format a trois segments. Attendu 401.'),
        array('signature', 'auth_signature_invalide', 'Jeton bien forme signe avec un autre secret. Attendu 403.'),
        array('expiree',   'auth_token_expire',       'Jeton valide mais exp depasse. Attendu 403 {message: token expire}.'),
    );
    foreach ($authCases as $entry) {
        $cases[] = array(
            'name'     => $entry[1],
            'groupe'   => 'authentification',
            'risque'   => 'sur',
            'auth'     => $entry[0],
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_avec_factures')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('abonne_avec_factures'),
            'note'     => $entry[2],
        );
    }

    return array_merge($cases, caracterisation_cases_factures($f), caracterisation_cases_paiement($f), caracterisation_cases_prepaye($f), caracterisation_cases_routeur());
}

/**
 * GET /get-factures
 *
 * @param array $f fixtures
 * @return array
 */
function caracterisation_cases_factures(array $f)
{
    return array(
        array(
            'name'     => 'factures_succes',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_avec_factures')),
            'comparer' => 'structure',
            'masquer'  => array(),
            'requiert' => array('abonne_avec_factures'),
            'note'     => 'TABLEAU NU, sans enveloppe. Cles attendues par element : '
                        . 'num_facture, a_payer, date_limit, prenom, nom. '
                        . 'Contenu variable donc comparaison structurelle.',
        ),
        array(
            'name'     => 'factures_format_court',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_format_court')),
            'comparer' => 'structure',
            'masquer'  => array(),
            'requiert' => array('abonne_format_court'),
            'note'     => 'Numero sur 6 chiffres : passe par la branche liste noire de verif_abonne.',
        ),
        array(
            'name'     => 'factures_parametre_manquant',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array(),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'Attendu 400 {statut, message: Parametre manquant} - la majuscule '
                        . 'est propre a cet endpoint, les autres ecrivent "parametre manquant".',
        ),
        array(
            'name'     => 'factures_abonne_inexistant',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_inexistant')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('abonne_inexistant'),
            'note'     => 'Attendu 404 {statut: erreur, message: abonne introuvable}.',
        ),
        array(
            'name'     => 'factures_abonne_liste_noire',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_liste_noire')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('abonne_liste_noire'),
            'note'     => 'Fige le comportement de la liste noire codee en dur, avant sa migration en base.',
        ),
        array(
            'name'     => 'factures_abonne_sans_facture',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_sans_facture')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('abonne_sans_facture'),
            'note'     => 'Attendu 404 {statut: erreur, message: pas de facture a payer}.',
        ),
        array(
            'name'     => 'factures_id_user_injecte',
            'groupe'   => 'get-factures',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'GET',
            'path'     => '/get-factures',
            'query'    => array('abonne' => carac_fx($f, 'abonne_avec_factures'), 'id_user' => 4),
            'comparer' => 'structure',
            'masquer'  => array(),
            'requiert' => array('abonne_avec_factures'),
            'note'     => 'FAILLE S2 : id_user fourni dans la requete court-circuite le JWT. '
                        . 'Documente le comportement AVANT correction (decision D2). Apres '
                        . 'correction la reponse HTTP doit rester identique, mais log_action_api '
                        . 'doit porter l identite du JWT et non 4 : a verifier en base, pas ici.',
        ),
    );
}

/**
 * POST /paye-facture et POST /annule-paiement
 *
 * @param array $f fixtures
 * @return array
 */
function caracterisation_cases_paiement(array $f)
{
    return array(
        array(
            'name'     => 'paye_parametre_manquant',
            'groupe'   => 'paye-facture',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/paye-facture',
            'body'     => array('facture' => carac_fx($f, 'facture_inexistante')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'num_ref absent. Attendu 400 {statut, message: parametre manquant}.',
        ),
        array(
            'name'     => 'paye_facture_inexistante',
            'groupe'   => 'paye-facture',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/paye-facture',
            'body'     => array('facture' => carac_fx($f, 'facture_inexistante'), 'num_ref' => 'TEST-CARAC-0001'),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('facture_inexistante'),
            'note'     => 'Attendu 404 {statut: erreur, message: facture introuvable}.',
        ),
        array(
            'name'     => 'paye_facture_deja_payee',
            'groupe'   => 'paye-facture',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/paye-facture',
            'body'     => array('facture' => carac_fx($f, 'facture_deja_payee'), 'num_ref' => 'TEST-CARAC-0002'),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('facture_deja_payee'),
            'note'     => 'Attendu 404 {statut: erreur, message: facture deja payee}.',
        ),
        array(
            'name'     => 'paye_succes',
            'groupe'   => 'paye-facture',
            'risque'   => 'mutant',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/paye-facture',
            'body'     => array(
                'facture' => carac_fx($f, 'facture_impayee_a_payer'),
                'num_ref' => 'TEST-CARAC-' . date('YmdHis'),
            ),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('facture_impayee_a_payer'),
            'note'     => 'ACQUITTE REELLEMENT LA FACTURE. Attendu 200 {statut: succes, message: '
                        . '"facure payee avec succes"} - la faute de frappe fait partie du contrat.',
        ),
        array(
            'name'     => 'annule_parametre_manquant',
            'groupe'   => 'annule-paiement',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/annule-paiement',
            'body'     => array(),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'Attendu 400 {statut, message: parametre manquant}.',
        ),
        array(
            'name'     => 'annule_transaction_introuvable',
            'groupe'   => 'annule-paiement',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/annule-paiement',
            'body'     => array('num_ref' => carac_fx($f, 'num_ref_inexistant')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('num_ref_inexistant'),
            'note'     => 'Attendu 404 {statut: erreur, message: Transaction introuvable}.',
        ),
        array(
            'name'     => 'annule_deja_annule',
            'groupe'   => 'annule-paiement',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/annule-paiement',
            'body'     => array('num_ref' => carac_fx($f, 'num_ref_deja_annule')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('num_ref_deja_annule'),
            'note'     => 'Attendu 404 {statut: erreur, message: Transaction deja annule}.',
        ),
        array(
            'name'     => 'annule_succes',
            'groupe'   => 'annule-paiement',
            'risque'   => 'mutant',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/annule-paiement',
            'body'     => array('num_ref' => carac_fx($f, 'num_ref_a_annuler')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('num_ref_a_annuler'),
            'note'     => 'INSERE REELLEMENT DANS acquittements_annulations.',
        ),
    );
}

/**
 * POST /achat-volume et POST /confirme-transc-volume
 *
 * @param array $f fixtures
 * @return array
 */
function caracterisation_cases_prepaye(array $f)
{
    return array(
        array(
            'name'     => 'achat_parametre_manquant',
            'groupe'   => 'achat-volume',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/achat-volume',
            'body'     => array('abonne' => carac_fx($f, 'abonne_prepaye')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'montant absent. Attendu 400 {statut, message: Parametres invalides}.',
        ),
        array(
            'name'     => 'achat_abonne_sans_compteur_prepaye',
            'groupe'   => 'achat-volume',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/achat-volume',
            'body'     => array('abonne' => carac_fx($f, 'abonne_sans_prepaye'), 'montant' => carac_fx($f, 'montant_achat_volume')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('abonne_sans_prepaye'),
            'note'     => 'Attendu 404 {statut: erreur, message: Cet abonne n a pas de compteur prepaye}.',
        ),
        array(
            'name'     => 'achat_succes',
            'groupe'   => 'achat-volume',
            'risque'   => 'externe',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/achat-volume',
            'body'     => array('abonne' => carac_fx($f, 'abonne_prepaye'), 'montant' => carac_fx($f, 'montant_achat_volume')),
            'comparer' => 'structure',
            'masquer'  => array('code_recharge', 'date_paiement', 'id_paiement'),
            'requiert' => array('abonne_prepaye'),
            'note'     => 'CREDITE UN COMPTEUR REEL chez le fournisseur et insere dans '
                        . 'paiements_prepayes. Ne jamais lancer en production.',
        ),
        array(
            'name'     => 'confirme_parametre_manquant',
            'groupe'   => 'confirme-transc-volume',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/confirme-transc-volume',
            'body'     => array('id_paiement' => carac_fx($f, 'id_paiement_inexistant')),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'num_transac absent. Attendu 400 {statut, message: Parametres invalides}.',
        ),
        array(
            'name'     => 'confirme_paiement_introuvable',
            'groupe'   => 'confirme-transc-volume',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/confirme-transc-volume',
            'body'     => array('id_paiement' => carac_fx($f, 'id_paiement_inexistant'), 'num_transac' => 'TEST-CARAC-0003'),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('id_paiement_inexistant'),
            'note'     => 'Attendu 404 {statut: erreur, message: transaction introuvable}. '
                        . 'Attention : le controleur journalise ici une variable non definie '
                        . '($num_facture) - bug B7, sans effet sur la reponse.',
        ),
        array(
            'name'     => 'confirme_deja_confirme',
            'groupe'   => 'confirme-transc-volume',
            'risque'   => 'sur',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/confirme-transc-volume',
            'body'     => array('id_paiement' => carac_fx($f, 'id_paiement_deja_confirme'), 'num_transac' => 'TEST-CARAC-0004'),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('id_paiement_deja_confirme'),
            'note'     => 'Attendu 409 {statut: erreur, message: transaction deja confirmee}.',
        ),
        array(
            'name'     => 'confirme_succes',
            'groupe'   => 'confirme-transc-volume',
            'risque'   => 'mutant',
            'auth'     => 'valide',
            'method'   => 'POST',
            'path'     => '/confirme-transc-volume',
            'body'     => array(
                'id_paiement' => carac_fx($f, 'id_paiement_a_confirmer'),
                'num_transac' => 'TEST-CARAC-' . date('YmdHis'),
            ),
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array('id_paiement_a_confirmer'),
            'note'     => 'MET A JOUR paiements_prepayes.num_transac_electronic.',
        ),
    );
}

/**
 * Comportement du routeur lui-meme.
 *
 * @return array
 */
function caracterisation_cases_routeur()
{
    return array(
        array(
            'name'     => 'route_inconnue',
            'groupe'   => 'routeur',
            'risque'   => 'sur',
            'auth'     => 'aucune',
            'method'   => 'GET',
            'path'     => '/route-qui-nexiste-pas',
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'Attendu 404 {message: page introuvable}. Le routeur emet un en-tete '
                        . '"Centent-Type" (coquille B13) au lieu de Content-Type : le corriger '
                        . 'changera les en-tetes vus par les consommateurs.',
        ),
        array(
            'name'     => 'route_methode_incorrecte',
            'groupe'   => 'routeur',
            'risque'   => 'sur',
            'auth'     => 'aucune',
            'method'   => 'GET',
            'path'     => '/paye-facture',
            'comparer' => 'complet',
            'masquer'  => array(),
            'requiert' => array(),
            'note'     => 'GET sur une route declaree en POST : AltoRouter ne matche pas.',
        ),
    );
}

