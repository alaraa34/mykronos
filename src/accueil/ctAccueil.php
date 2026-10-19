<?php
declare(strict_types=1);
namespace theBand\src\php\accueil;


use shared\php\classes\socle\Login                      as Login;
use shared\php\classes\socle\User                       as User;
use chords\php\Chords                                   as Chords;
use shared\php\toolbox\Toolbox_adressage                as TbAdressage;
use shared\php\toolbox\Toolbox                          as Tbx;
use shared\php\classes\personalisation\Parametre        as Parametre;
use shared\php\modale\Toolbox_modal                     as TbModal;
use shared\php\toolbox\Toolbox_liste                    as TbListe;
use shared\php\bricks\Brick_table                       as BkTable;
use shared\php\toolbox\Toolbox_mail                     as TbMail;
use theBand\src\php\socle\UserTheBand                   as UserTB;
use shared\php\classes\lien\TypeLienUsage_ass           as TypeLienUsage;
use shared\php\classes\telechargement\Telechargement    as Telechargement;
use theBand\src\php\song\Song                           as Song;
use theBand\src\php\song\Repertoire                     as Repertoire;
use theBand\src\php\proposition\Proposition             as Proposition;
use theBand\src\php\evenement\Evenement_Concert         as Evenement_Concert;
use theBand\src\php\evenement\Evenement_Repetition      as Evenement_Repetition;
use theBand\src\php\etablissement\Etablissement         as Etablissement;

function ctAccueil() {
    $mode = "accueil";
    require('tpAccueil.php');
}

 
function ctLoginFromToolbar() {
//réception du bouton d'authentification de la barre d'outils
// déconnexion si clic sur se déconnecter
    Login::loginControlDemand(__NAMESPACE__);
}

function ctLoginUser() {
//réception du formulaire d'authentification 
    Login::loginControlReception(__NAMESPACE__);
}

function ctEnConstruction() {
//affichage emplate en construction
    $mode = "enConstruction";
    require('accueil.php');
}
function accesAdministrateur(): bool {
//true si un administrateur est connecté, sinon affiche un message de refus
    if (User::estAdministrateur()) {
        return true;
    }
    $content = Tbx::messageColorer(false, messageKO: "Action réservée à un administrateur connecté.");
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
    return false;
}

function ctRAZ() {
//efface la base de donnée
//appel ?fct=RAZ&ctr=accueil  - réservé à un administrateur connecté
    if (!accesAdministrateur()) {return;}
    require_once(ROOT_PATH . 'shared/php/database/ctUtilitaires.php');
    \shared\php\database\viderTablesAvecPrefixe(exclusions: ['nomenclature','menu','user','requete','parametre','lien_type','lien_type_usage']);
    echo("Terminé");
}

function ctRenommer() {
//attention dans index il faut que le prefixe soit à blanc
//renomme toutes les tables : une fois fait il faut changer le nom du préfixe dans index
//appel https://kontouma.fr/theBand/index.php?fct=renommer&ctr=accueil  - réservé à un administrateur connecté
    if (!accesAdministrateur()) {return;}
    $fichier = ROOT_PATH . 'shared/php/database/ctUtilitaires.php';
    if (file_exists($fichier)) {
        require_once($fichier);
        \shared\php\database\ajouterPrefixeTables(prefixe:"tb_" );
        echo("Terminé");
    }
    else{die("fichier ". $fichier . "introuvable");}
}

function ctManifest() {
//manifeste PWA (application installable sur smartphone)
//appel index.php?ctr=accueil&fct=manifest depuis tpLayout - le nom du groupe vient de GENERAL/NOMGROUPE
    $nomGroupe = (string)Parametre::mdParametreGetDetail("GENERAL", "NOMGROUPE", "valeurA");

    $manifeste = [
        'name'             => $nomGroupe !== '' ? "theBand – " . $nomGroupe : "theBand",
        'short_name'       => $nomGroupe !== '' ? $nomGroupe : "theBand",
        'description'      => "Organisation du groupe : répertoire, setlists, concerts, planning.",
        'lang'             => "fr",
        'start_url'        => "./",
        'scope'            => "./",
        'display'          => "standalone",
        'orientation'      => "portrait",
        'background_color' => "#b76533",
        'theme_color'      => "#b76533",
        'icons' => [
            ['src' => "icons/icon-192.png",          'sizes' => "192x192", 'type' => "image/png"],
            ['src' => "icons/icon-512.png",          'sizes' => "512x512", 'type' => "image/png"],
            ['src' => "icons/icon-512-maskable.png", 'sizes' => "512x512", 'type' => "image/png", 'purpose' => "maskable"],
        ],
    ];
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode($manifeste, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit; //pas de layout
}

function ctAccordDetecter(){
    $content = Chords::visualise();
    $script =  Tbx::includeJS(['piano_chords'],"chords");
    $css = Tbx::includeCSS(['piano_chords'],"chords");
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

function ctAccordIdentifier(){
    $content = Chords::detect();
    $script =  Tbx::includeJS(['piano_chords'],"chords");
    $css = Tbx::includeCSS(['piano_chords'],"chords");
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

function ctGammesEtModes(){
    $content = Chords::scales();
    $script =  Tbx::includeJS(['piano_chords'],"chords");
    $css = Tbx::includeCSS(['piano_chords'],"chords");
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

/*******************************************************************************
 * ADMINISTRATION DES UTILISATEURS (réservé aux administrateurs)
 ******************************************************************************/
function ctUtilisateurLister(): void {
//liste de tous les utilisateurs avec modification et suppression
    if (!accesAdministrateur()) {return;}
    afficherListeUtilisateurs();
}

function afficherListeUtilisateurs(string $message = "", bool $succes = true): void {
//affichage de la liste des utilisateurs, précédée éventuellement d'un message de résultat
    $messageBarreMenu = "Utilisateurs";
    $script = Tbx::includeJS('utils');  //traitement des actions de la liste
    $infos = User::listeTous();
    foreach ($infos as $index => $info) {
        $infos[$index]['actifTexte'] = $info['actif'] ? "Oui" : "Non";
        $infos[$index]['adminTexte'] = $info['administrateur'] ? "Oui" : "Non";
    }
    $actions = [
        ['texte' => "Modifier l'utilisateur", 'logoClass' => 'fa-regular fa-pen-to-square', 'href' => 'accueil;editer;utilisateur'],
        ['texte' => "Réinitialiser le mot de passe et l'envoyer par mail", 'logoClass' => 'bi bi-envelope-arrow-up', 'modale' => 'accueil;envoyer;utilisateur',
            'message' => "Réinitialiser le mot de passe de cet utilisateur (prénom en minuscules + nombre de lettres) et le lui envoyer par mail ? L'ancien ne fonctionnera plus."],
        ['texte' => "Supprimer l'utilisateur", 'logoClass' => 'bi bi-trash', 'modale' => 'accueil;supprimer;utilisateur',
            'message' => "Supprimer définitivement cet utilisateur ? Pour lui retirer seulement l'accès, décochez plutôt « Actif »."]
    ];
    $infosColonnes[] = ['zone' => BkTable::COLONNE_NUMERO];
    $infosColonnes[] = ['zone' => 'nom', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'prenom', 'titre' => 'Prénom', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'abrev', 'titre' => 'Abrév.', 'fonction' => TbListe::FONCTION_VASN, 'align' => 'MC'];
    $infosColonnes[] = ['zone' => 'pseudo', 'titre' => 'Identifiant', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'mail', 'titre' => 'Mail', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'actifTexte', 'titre' => 'Actif', 'align' => 'MC'];
    $infosColonnes[] = ['zone' => 'adminTexte', 'titre' => 'Admin', 'align' => 'MC'];
    $content = (strlen($message) > 0 ? Tbx::messageColorer($succes, $message, $message) : "")
             . TbListe::constituerListe($infos, $infosColonnes, $actions);
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

function ctUtilisateurEditer(int $idUtilisateur = 0): void {
//création (id = 0) ou modification d'un utilisateur, avec changement éventuel du mot de passe
    if (!accesAdministrateur()) {return;}
    $utilisateur = new User($idUtilisateur);
    if ($idUtilisateur > 0 && $utilisateur->pseudo === "") {
        afficherListeUtilisateurs("Utilisateur introuvable.", false);
        return;
    }
    afficherFormulaireUtilisateur($utilisateur);
}

function afficherFormulaireUtilisateur(User $utilisateur, string $messageErreur = ""): void {
//affichage du formulaire utilisateur (aussi réaffiché avec la saisie en cas d'erreur)
    $messageBarreMenu = $utilisateur->id === 0 ? "Nouvel utilisateur" : "Modification de l'utilisateur " . htmlspecialchars($utilisateur->prenom);
    $avatars = listeAvatars();
    $motDePasseSuggere = User::genererMotDePasse();
    require('tpUtilisateurDetail.php');
}

function listeAvatars(): array {
//images disponibles dans theBand/images pour l'avatar
    $avatars = [];
    foreach (glob(ROOT_PATH . PROJET . '/images/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $fichier) {
        $avatars[] = basename($fichier);
    }
    sort($avatars, SORT_NATURAL | SORT_FLAG_CASE);
    return $avatars;
}

function ctUtilisateurMAJ(): void {
//enregistrement du formulaire utilisateur
    if (!accesAdministrateur()) {return;}
    $utilisateur = new User(TbAdressage::getPost("I", "idUtilisateur"));
    $utilisateur->nom            = trim(TbAdressage::getPost("S", "nom"));
    $utilisateur->prenom         = trim(TbAdressage::getPost("S", "prenom"));
    $utilisateur->abrev          = strtoupper(trim(TbAdressage::getPost("S", "abrev")));
    $utilisateur->pseudo         = trim(TbAdressage::getPost("S", "pseudo"));
    $utilisateur->mail           = trim(TbAdressage::getPost("S", "mail"));
    $utilisateur->avatar         = TbAdressage::getPost("S", "avatar");
    $utilisateur->actif          = TbAdressage::getPost("C", "actif") ? 1 : 0;
    $utilisateur->administrateur = TbAdressage::getPost("C", "administrateur") ? 1 : 0;
    $motDePasse = TbAdressage::getPost("S", "motDePasse");
    $creation = $utilisateur->id === 0;

    //l'administrateur connecté ne peut pas se retirer ses propres droits (risque de ne plus pouvoir se connecter)
    if ($utilisateur->id === (int)User::connectUserGetInfo("id")) {
        $utilisateur->actif = 1;
        $utilisateur->administrateur = 1;
    }
    //contrôles
    $erreur = "";
    if ($utilisateur->nom === "" || $utilisateur->prenom === "" || $utilisateur->abrev === "" || $utilisateur->pseudo === "") {
        $erreur = "Le nom, le prénom, l'abréviation et l'identifiant sont obligatoires.";
    }
    elseif (strlen($utilisateur->abrev) > 2) {
        $erreur = "L'abréviation fait 2 caractères au maximum.";
    }
    elseif ($creation && $motDePasse === "") {
        $erreur = "Un mot de passe est obligatoire pour un nouvel utilisateur.";
    }
    elseif ($motDePasse !== "" && strlen($motDePasse) < 6) {
        $erreur = "Le mot de passe doit faire au moins 6 caractères.";
    }
    else {
        $erreur = $utilisateur->controlerUnicite();
    }
    if ($erreur !== "") {
        afficherFormulaireUtilisateur($utilisateur, $erreur);
        return;
    }
    //enregistrement
    $retour = $utilisateur->enregistrer();
    if ($retour && $motDePasse !== "") {$retour = User::passwordEnregistrer($utilisateur->id, $motDePasse);}
    if ($retour && $creation) {
        //création de ses données groupe (rôle à renseigner ensuite dans la liste des musiciens)
        $musicien = new UserTB($utilisateur->id);
        $retour = $musicien->majDonneesGroupe();
    }
    //résultat : le mot de passe saisi est affiché une seule fois pour pouvoir le communiquer
    $nom = htmlspecialchars($utilisateur->prenom . " " . $utilisateur->nom);
    $message = $creation ? "Utilisateur " . $nom . " créé." : "Modifications de " . $nom . " enregistrées.";
    if ($retour && $motDePasse !== "") {
        $message .= "<br>Mot de passe à lui communiquer : <strong>" . htmlspecialchars($motDePasse) . "</strong> (il ne sera plus affiché)";
    }
    afficherListeUtilisateurs($retour ? $message : "Erreur lors de l'enregistrement de l'utilisateur.", $retour);
}

function ctUtilisateurEnvoyer(): void {
//réinitialise le mot de passe à sa valeur par défaut (prénom en minuscules + nombre de lettres, ex. alain5)
//et l'envoie par mail à l'utilisateur, en retour de la modale de confirmation
//le mot de passe n'est remplacé que si le mail est bien parti
    if (!accesAdministrateur()) {return;}
    $utilisateur = new User(TbModal::modalGetIdFromModal());
    $nom = htmlspecialchars($utilisateur->prenom . " " . $utilisateur->nom);
    if ($utilisateur->pseudo === "") {
        afficherListeUtilisateurs("Utilisateur introuvable.", false);
        return;
    }
    if (!filter_var($utilisateur->mail, FILTER_VALIDATE_EMAIL)) {
        afficherListeUtilisateurs("Pas d'adresse mail valide pour " . $nom . " : renseignez-la d'abord dans sa fiche.", false);
        return;
    }
    $motDePasse = $utilisateur->motDePasseParDefaut();
    $texte = "A ta demande tes infos d'access User:" . $utilisateur->pseudo . "   PWD:" . $motDePasse;
    if (!TbMail::envoyer($utilisateur->mail, "info connexion", $texte)) {
        afficherListeUtilisateurs("Le mail n'a pas pu être envoyé à " . $nom . ". Son mot de passe n'a pas été changé.<br>Motif : "
            . htmlspecialchars(TbMail::$derniereErreur), false);
        return;
    }
    $retour = User::passwordEnregistrer($utilisateur->id, $motDePasse);
    afficherListeUtilisateurs($retour ? "Nouveau mot de passe envoyé à " . $nom . " (" . htmlspecialchars($utilisateur->mail) . ")."
                                      : "Mail envoyé mais erreur lors de l'enregistrement du mot de passe de " . $nom . " : recommencez.", $retour);
}

function ctUtilisateurSupprimer(): void {
//suppression d'un utilisateur, en retour de la modale de confirmation
    if (!accesAdministrateur()) {return;}
    $idUtilisateur = TbModal::modalGetIdFromModal();
    if ($idUtilisateur === (int)User::connectUserGetInfo("id")) {
        afficherListeUtilisateurs("Vous ne pouvez pas supprimer votre propre compte.", false);
        return;
    }
    $utilisateur = new User($idUtilisateur);
    $nom = htmlspecialchars($utilisateur->prenom . " " . $utilisateur->nom);
    $retour = UserTB::supprimerDonneesGroupe($idUtilisateur) && $utilisateur->delete();
    afficherListeUtilisateurs($retour ? "Utilisateur " . $nom . " supprimé." : "Erreur lors de la suppression de " . $nom . ".", $retour);
}

/*******************************************************************************
 * DONNEES GROUPE DES MUSICIENS (table tb_user_theband, réservé aux administrateurs)
 ******************************************************************************/
function ctMusicienLister(): void {
//liste des utilisateurs avec leurs données groupe, modification seulement
    if (!accesAdministrateur()) {return;}
    afficherListeMusiciens();
}

function afficherListeMusiciens(string $message = "", bool $succes = true): void {
    $messageBarreMenu = "Musiciens : données du groupe";
    $script = Tbx::includeJS('utils');  //traitement des actions de la liste
    UserTB::synchroniserAvecUser();     //une ligne tb_user_theband par utilisateur, ni plus ni moins
    $infos = UserTB::listePourAdministration();
    foreach ($infos as $index => $info) {
        $infos[$index]['roleTexte']  = UserTB::libelleRole((int)$info['role']);
        $infos[$index]['actifTexte'] = $info['actif'] ? "Oui" : "Non";
    }
    $actions = [['texte' => 'Modifier les données groupe', 'logoClass' => 'fa-regular fa-pen-to-square', 'href' => 'accueil;editer;musicien']];
    $infosColonnes[] = ['zone' => BkTable::COLONNE_NUMERO];
    $infosColonnes[] = ['zone' => 'nom', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'prenom', 'titre' => 'Prénom', 'fonction' => TbListe::FONCTION_VASN];
    $infosColonnes[] = ['zone' => 'abrev', 'titre' => 'Abrév.', 'fonction' => TbListe::FONCTION_VASN, 'align' => 'MC'];
    $infosColonnes[] = ['zone' => 'roleTexte', 'titre' => 'Rôle'];
    $infosColonnes[] = ['zone' => 'coeff', 'titre' => 'Coeff.<br>maîtrise', 'fonction' => TbListe::FONCTION_VASN, 'align' => 'MC'];
    $infosColonnes[] = ['zone' => 'actifTexte', 'titre' => 'Actif', 'align' => 'MC'];
    $content = (strlen($message) > 0 ? Tbx::messageColorer($succes, $message, $message) : "")
             . TbListe::constituerListe($infos, $infosColonnes, $actions);
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

function ctMusicienEditer(int $idUtilisateur = 0): void {
//modification des données groupe d'un utilisateur existant
    if (!accesAdministrateur()) {return;}
    $musicien = new UserTB($idUtilisateur);
    if ($musicien->id === 0 || $musicien->nom === "") {
        afficherListeMusiciens("Utilisateur introuvable.", false);
        return;
    }
    $messageBarreMenu = "Données groupe de " . htmlspecialchars($musicien->prenom . " " . $musicien->nom);
    $roles = UserTB::listeRoles();
    require('tpMusicienDetail.php');
}

function ctMusicienMAJ(): void {
//enregistrement des données groupe : seule la table tb_user_theband est mise à jour
    if (!accesAdministrateur()) {return;}
    $musicien = new UserTB(TbAdressage::getPost("I", "idUtilisateur"));
    $musicien->role  = TbAdressage::getPost("I", "role");
    $musicien->coeff = TbAdressage::getPost("F", "coeff");
    $retour = $musicien->id > 0 && $musicien->majDonneesGroupe();
    afficherListeMusiciens($retour ? "Données groupe de " . htmlspecialchars($musicien->prenom . " " . $musicien->nom) . " enregistrées."
                                   : "Erreur lors de l'enregistrement des données groupe.", $retour);
}

/*******************************************************************************
 * TYPES DE LIEN ADMIS PAR SUJET (table tb_lien_type_usage, réservé aux administrateurs)
 * Les types de lien sont communs à toutes les applications (sh_lien_type)
 ******************************************************************************/
function sujetsLien(): array {
//sujets de theBand qui utilisent des liens : constante SUJET_LIEN de la classe => libellé affiché
    return [
        Repertoire::SUJET_LIEN           => "Titres",
        Song::SUJET_LIEN_PLAYER          => "Players",
        Proposition::SUJET_LIEN          => "Propositions",
        Evenement_Repetition::SUJET_LIEN => "Répétitions",
        Evenement_Concert::SUJET_LIEN    => "Concerts",
        Etablissement::SUJET_LIEN        => "Etablissements",
        Telechargement::SUJET_LIEN       => "Téléchargements",
    ];
}

function ctLienUsageEditer(): void {
//grille des types de lien admis par sujet
    if (!accesAdministrateur()) {return;}
    afficherGrilleLienUsage();
}

function afficherGrilleLienUsage(string $message = "", bool $succes = true): void {
    $messageBarreMenu = "Types de lien par sujet";
    $content = (strlen($message) > 0 ? Tbx::messageColorer($succes, $message, $message) : "")
             . TypeLienUsage::renderGrille(sujetsLien(), "accueil", "lienUsageMAJ");
    require(TbAdressage::projetGetLayout(__NAMESPACE__));
}

function ctLienUsageMAJ(): void {
//enregistrement de la grille
    if (!accesAdministrateur()) {return;}
    $retour = TypeLienUsage::enregistrer(sujetsLien());
    afficherGrilleLienUsage($retour ? "Types de lien par sujet enregistrés."
                                    : "Erreur lors de l'enregistrement : aucune modification n'a été faite.", $retour);
}
