<?php
/**
 * Configuration du harnais de caracterisation.
 *
 * Copier ce fichier en config.php et renseigner les valeurs.
 * CONFIG.PHP NE DOIT JAMAIS ETRE PARTAGE : il contient des identifiants, et
 * souvent des references a des donnees reelles.
 *
 * Les fixtures laissees vides desactivent automatiquement les cas qui en
 * dependent ; le harnais les signale comme IGNORE plutot que d'echouer. Vous
 * pouvez donc demarrer avec un sous-ensemble et completer au fur et a mesure.
 */
return array(

    // Racine de l'API a caracteriser, sans barre finale.
    'base_url' => 'http://localhost/mon-projet',

    // Secret de signature JWT du projet.
    //
    // NE SERT QU'A FORGER LES JETONS DEGRADES des cas d'authentification --
    // expire, mal signe, malforme -- pour verifier que l'API les refuse.
    //
    // Laissez-le vide si vos cas n'en ont pas besoin. N'ECRIVEZ JAMAIS ICI
    // une valeur de repli : un secret par defaut qui traine dans un fichier
    // d'exemple finit toujours par se retrouver en production.
    'jwt_secret' => '',

    // Un compte valide de l'API.
    'auth' => array(
        'username' => '',
        'pwd'      => '',
    ),

    'timeout' => 60,

    // ---- Fixtures -----------------------------------------------------------
    //
    // Les identifiants reels que les cas utilisent. Ils dependent entierement
    // de votre projet ; ceux-ci ne sont qu'une illustration de la maniere de
    // les organiser -- par nature, et par niveau de risque.
    //
    // ECRIVEZ EN COMMENTAIRE, A COTE DE CHAQUE FIXTURE, LA REQUETE SQL QUI LA
    // TROUVE. Sans cela, personne ne saura les renouveler dans six mois, et le
    // harnais deviendra inutilisable au moment ou l'on en aura le plus besoin.
    'fixtures' => array(

        // ---- Lecture seule : rejouables a volonte ---------------------------
        // SELECT numero FROM clients WHERE actif = 1 LIMIT 1;
        'client_existant'    => '',
        // Un identifiant qui n'existe dans aucune table.
        'client_inexistant'  => 'INEXISTANT-0001',

        // ---- Cas MUTANTS : consommes par l'execution ------------------------
        // Ces valeurs sont detruites par le passage du test -- la facture est
        // reellement payee, la transaction reellement annulee. Renouvelez-les
        // avant chaque execution, ou restaurez un instantane de la base.
        'facture_a_payer'    => '',

        // ---- Cas EXTERNES : appellent un tiers reel -------------------------
        // Ce qui est consomme ici l'est pour de bon, chez le fournisseur.
        // A NE JAMAIS ACTIVER EN PRODUCTION.
        'montant_test'       => 1000,
    ),
);

