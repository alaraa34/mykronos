<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Recherche de sons
 *  - chaque mot saisi doit se trouver dans le nom du son, sa catégorie ou sa sous-catégorie
 *    (sans tenir compte des majuscules ni des accents)
 *  - filtres facultatifs : type (Program, Combi...) et provenance du fichier :
 *    SAUF_KRONOS (défaut) tout sauf les exports de mes Kronos, KRONOS seulement mes Kronos, TOUTES, ou une typologie
 *  - pour chaque combi trouvée : ses timbres (program, zone de clavier, vélocité) ; pour chaque program : les combis qui l'utilisent
 *  - présence sur mes Kronos (K2 / K3) et emplacements de leurs set lists, jugés par le nom du son
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use mykronos\src\php\fichier\Fichier                as Fichier;

class Recherche
{
    public const MAXIMUM        = 200;                  //nombre de sons affichés au plus
    public const TABLE_LIENS    = PREFIXE_BDD . "combi_utilise_program";
    public const TABLE_SETLISTS = PREFIXE_BDD . "setlist";
    public const TABLE_SLOTS    = PREFIXE_BDD . "setlist_slot";
    public const SAUF_KRONOS    = -1;                   //provenance : tout sauf fromK2 / fromK3 (par défaut)
    public const KRONOS         = -2;                   //provenance : seulement fromK2 / fromK3
    public const TOUTES         = 0;
    private const PREFIXE_KRONOS = "from";              //typologies des exports de mes Kronos

    public const MOTEURS = ["HD-1" => "HD-1 (échantillons)", "EXi" => "EXi (moteurs modélisés)"];

    public static function rechercher(string $texte, int $idType = 0, int $idTypologie = self::SAUF_KRONOS,
                                      array $options = []): array {
    //retourne ['sons' => [...], 'trop' => bool] - chaque son a en plus 'liens' (programs utilisés ou combis utilisatrices)
    //$options : 'moteur' => HD-1 ou EXi (Programs), 'favoris' => true pour les favoris du Kronos seulement
    //sans texte, la recherche n'est lancée que pour les favoris
        $mots = self::mots($texte);
        $moteur = (string)($options['moteur'] ?? "");
        $favoris = (bool)($options['favoris'] ?? false);
        if (count($mots) === 0 && !$favoris) {return ['sons' => [], 'trop' => false];}

        $conditions = ["1 = 1"];
        $parametres = [];
        foreach ($mots as $mot) {
            $conditions[] = "(so.nom LIKE ? OR sc.libelle LIKE ? OR c.libelle LIKE ?)";
            $motif = "%" . addcslashes($mot, "%_\\") . "%";
            array_push($parametres, $motif, $motif, $motif);
        }
        if ($idType > 0)      {$conditions[] = "so.idType = ?";    $parametres[] = $idType;}
        if (isset(self::MOTEURS[$moteur])) {$conditions[] = "so.moteur LIKE ?"; $parametres[] = $moteur . "%";}
        if ($favoris)         {$conditions[] = "so.favori = 1";}
        if ($idTypologie > 0) {$conditions[] = "f.typologie = ?";  $parametres[] = $idTypologie;}
        if ($idTypologie === self::SAUF_KRONOS) {$conditions[] = "(ty.nom IS NULL OR ty.nom NOT LIKE ?)"; $parametres[] = self::PREFIXE_KRONOS . "%";}
        if ($idTypologie === self::KRONOS)      {$conditions[] = "ty.nom LIKE ?";                         $parametres[] = self::PREFIXE_KRONOS . "%";}

        $sons = self::lireSons(implode(" AND ", $conditions), $parametres, self::MAXIMUM + 1);
        $trop = count($sons) > self::MAXIMUM;
        $sons = array_slice($sons, 0, self::MAXIMUM);
        return ['sons' => self::ajouterLiens($sons), 'trop' => $trop];
    }

    public static function combi(int $idSon): array {
    //une combi avec sa composition et sa présence sur mes Kronos (fiche à imprimer), [] si inconnue
        $idCombi = array_flip(Categorie::types())['Combi'] ?? 0;
        $sons = self::lireSons("so.id = ? AND so.idType = ?", [$idSon, $idCombi], 1);
        if (count($sons) === 0) {return [];}
        $resultat = self::ajouterPresenceKronos(['sons' => self::ajouterLiens($sons), 'trop' => false]);
        return ['combi' => $resultat['sons'][0], 'kronos' => $resultat['kronos']];
    }

    private static function lireSons(string $where, array $parametres, int $limite): array {
    //sons avec type, catégorie, fichier et provenance
        return Model::mdRequeteLister(
            "SELECT so.id, so.nom, so.banque, so.idType, so.idFichier, so.favori, so.moteur, so.tempo, t.valeurA AS type,"
          . " sc.libelle AS sousCategorie, c.libelle AS categorie,"
          . " f.libelle AS fichier, f.emplacement, f.exclus, ty.nom AS typologie"
          . " FROM " . Son::TABLE . " so"
          . " JOIN " . Nomenclature::TABLE . " t ON t.id = so.idType"
          . " JOIN " . Fichier::TABLE . " f ON f.id = so.idFichier"
          . " LEFT JOIN " . Nomenclature::TABLE . " ty ON ty.id = f.typologie"
          . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = so.idSsCategorie"
          . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
          . " WHERE " . $where
          . " ORDER BY so.nom, f.libelle, so.banque LIMIT " . $limite,
            $parametres);
    }

    public static function mots(string $texte): array {
    //mots de la recherche, sans doublon
        return array_values(array_unique(preg_split('/\s+/u', trim($texte), -1, PREG_SPLIT_NO_EMPTY)));
    }

    private static function ajouterLiens(array $sons): array {
    //combi -> programs utilisés (avec leur nom s'ils sont dans le même PCG), program -> combis qui l'utilisent
        $types = array_flip(Categorie::types());                 //Program / Combi => id
        $idProgram = $types['Program'] ?? 0;
        $idCombi   = $types['Combi'] ?? 0;
        foreach ($sons as &$son) {
            $son['liens'] = [];
            if ((int)$son['idType'] === $idCombi) {
                $son['liens'] = Model::mdRequeteLister(
                    "SELECT l.timbre, l.canal, l.volume, l.banqueProgram AS banque, l.noteBasse, l.noteHaute, l.veloBasse, l.veloHaute,"
                  . " p.nom, c.libelle AS categorie, sc.libelle AS sousCategorie"
                  . " FROM " . self::TABLE_LIENS . " l"
                  . " LEFT JOIN " . Son::TABLE . " p ON p.idFichier = l.idFichier AND p.banque = l.banqueProgram AND p.idType = ?"
                  . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = p.idSsCategorie"
                  . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
                  . " WHERE l.idFichier = ? AND l.banqueCombi = ? ORDER BY l.timbre, l.id",
                    [$idProgram, (int)$son['idFichier'], $son['banque']]);
            } elseif ((int)$son['idType'] === $idProgram) {
                $son['liens'] = Model::mdRequeteLister(
                    "SELECT DISTINCT l.banqueCombi AS banque, cb.nom, c.libelle AS categorie, sc.libelle AS sousCategorie"
                  . " FROM " . self::TABLE_LIENS . " l"
                  . " LEFT JOIN " . Son::TABLE . " cb ON cb.idFichier = l.idFichier AND cb.banque = l.banqueCombi AND cb.idType = ?"
                  . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = cb.idSsCategorie"
                  . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
                  . " WHERE l.idFichier = ? AND l.banqueProgram = ? ORDER BY l.banqueCombi",
                    [$idCombi, (int)$son['idFichier'], $son['banque']]);
            }
        }
        unset($son);
        return $sons;
    }

    public static function listeTypes(): array {
    //types de son présents dans la nomenclature : id => libellé
        return array_column(Nomenclature::mdNomenclatureGetListe("TYPE", "valeurA"), 'zone', 'identifiant');
    }

    public static function listeTypologies(): array {
        return array_column(Nomenclature::mdNomenclatureGetListe(Fichier::GROUPE_TYPOLOGIE, "nom"), 'zone', 'identifiant');
    }

    //------------------------------------------------------------------------------------------------
    // RECHERCHE DE COMBINAISONS : composition et présence sur mes Kronos
    //------------------------------------------------------------------------------------------------
    public static function rechercherCombinaisons(string $texte, int $idTypologie = self::SAUF_KRONOS, bool $favoris = false): array {
    //combis trouvées, chacune avec 'kronos' (banques de la combi sur K2 / K3)
    //et 'programs' : [banque d'origine, nom, catégorie, 'kronos' => ['K2' => [banques], 'K3' => [banques]]]
    //la présence sur un Kronos se juge par le nom, dans le dernier export importé de ce Kronos
        $idCombi = array_flip(Categorie::types())['Combi'] ?? 0;
        return self::ajouterPresenceKronos(self::rechercher($texte, $idCombi, $idTypologie, ['favoris' => $favoris]));
    }

    public static function ajouterPresenceKronos(array $resultat): array {
    //complète les Programs et Combis d'un résultat de recherche :
    //  'kronos'   : banques du son sur K2 / K3, 'setlists' : emplacements de set lists de K2 / K3 qui l'appellent
    //  'programs' (combis) : timbres avec banque d'origine, zone, vélocité et banques sur K2 / K3, 'disposition' (split, layer)
        $types = array_flip(Categorie::types());
        $idCombi = $types['Combi'] ?? 0;
        $idProgram = $types['Program'] ?? 0;
        $kronos = self::exportsKronos();
        $resultat['kronos'] = $kronos;
        if (count($resultat['sons']) === 0) {return $resultat;}

        //contenu des exports chargé une fois : nom => banques, et banque => nom (programs d'usine)
        $index = [];
        foreach ($kronos as $machine => $idFichier) {
            $index[$machine] = ['noms' => [$idProgram => [], $idCombi => []], 'banques' => []];
            foreach (Model::mdRequeteLister("SELECT idType, banque, nom FROM " . Son::TABLE
                                          . " WHERE idFichier = ? AND idType IN (?, ?)", [$idFichier, $idProgram, $idCombi]) as $son) {
                $index[$machine]['noms'][(int)$son['idType']][self::cle((string)$son['nom'])][] = $son['banque'];
                if ((int)$son['idType'] === $idProgram) {$index[$machine]['banques'][$son['banque']] = $son['nom'];}
            }
            //emplacements de set lists : idType / banque => [set list, n°, nom]
            $index[$machine]['slots'] = [];
            foreach (Model::mdRequeteLister(
                    "SELECT sl.nom AS setlist, sl.numero AS numeroSetlist, s.numero, s.nom, s.idType, s.banque"
                  . " FROM " . self::TABLE_SLOTS . " s JOIN " . self::TABLE_SETLISTS . " sl ON sl.id = s.idSetlist"
                  . " WHERE sl.idFichier = ? AND s.banque IS NOT NULL ORDER BY sl.numero, s.numero", [$idFichier]) as $slot) {
                $index[$machine]['slots'][(int)$slot['idType']][$slot['banque']][] = $slot;
            }
        }
        $surKronos = function (int $idType, ?string $nom) use ($kronos, $index): array {
            $banques = [];
            foreach (array_keys($kronos) as $machine) {
                $liste = $nom === null ? [] : ($index[$machine]['noms'][$idType][self::cle($nom)] ?? []);
                sort($liste);
                $banques[$machine] = $liste;
            }
            return $banques;
        };

        $dansSetlists = function (int $idType, array $banquesParMachine) use ($index): array {
        //emplacements de set lists qui appellent ces banques : [['machine', 'setlist', 'numeroSetlist', 'numero', 'nom'], ...]
            $slots = [];
            foreach ($banquesParMachine as $machine => $banques) {
                foreach ($banques as $banque) {
                    foreach ($index[$machine]['slots'][$idType][$banque] ?? [] as $slot) {
                        $slots[] = ['machine' => $machine] + $slot;
                    }
                }
            }
            return $slots;
        };

        foreach ($resultat['sons'] as &$son) {
            if ((int)$son['idType'] === $idProgram) {
                $son['kronos'] = $surKronos($idProgram, (string)$son['nom']);
                $son['setlists'] = $dansSetlists($idProgram, $son['kronos']);
            }
        }
        unset($son);

        foreach ($resultat['sons'] as &$combi) {
            if ((int)$combi['idType'] !== $idCombi) {continue;}
            $combi['kronos'] = $surKronos($idCombi, (string)$combi['nom']);
            $combi['setlists'] = $dansSetlists($idCombi, $combi['kronos']);
            $combi['programs'] = [];
            foreach ($combi['liens'] as $lien) {
                $nom = $lien['nom'];
                if ($nom === null) {
                    //program absent du PCG d'origine (son d'usine, banque I-...) : nom repris d'un export Kronos, même banque
                    foreach (array_keys($kronos) as $machine) {$nom ??= $index[$machine]['banques'][$lien['banque']] ?? null;}
                }
                $combi['programs'][] = [
                    'timbre'    => (int)$lien['timbre'],
                    'canal'     => $lien['canal'] === null ? null : (int)$lien['canal'],
                    'volume'    => $lien['volume'] === null ? null : (int)$lien['volume'],
                    'zone'      => [$lien['noteBasse'], $lien['noteHaute']],
                    'velocite'  => [$lien['veloBasse'], $lien['veloHaute']],
                    'banque'    => $lien['banque'],
                    'nom'       => $nom,
                    'origine'   => $lien['nom'] !== null,           //false : nom repris d'un export Kronos
                    'categorie' => $lien['categorie'] === null ? "" : $lien['categorie'] . " / " . $lien['sousCategorie'],
                    'kronos'    => $surKronos($idProgram, $nom),
                ];
            }
            $combi['disposition'] = self::disposition($combi['programs']);
        }
        unset($combi);
        return $resultat;
    }

    private static function disposition(array $timbres): string {
    //"Split" : zones de clavier côte à côte, "Layer" : sons superposés sur la même zone, "Split + layer" : les deux
        $zones = array_values(array_filter(array_column($timbres, 'zone'), fn($z) => $z[0] !== null && $z[1] !== null));
        if (count($zones) < 2) {return "";}
        $separees = $superposees = false;
        for ($a = 0; $a < count($zones); $a++) {
            for ($b = $a + 1; $b < count($zones); $b++) {
                $chevauchement = max($zones[$a][0], $zones[$b][0]) <= min($zones[$a][1], $zones[$b][1]);
                $chevauchement ? $superposees = true : $separees = true;
            }
        }
        return match (true) {$separees && $superposees => "Split + layer", $separees => "Split", default => "Layer"};
    }

    public static function nomNote(?int $note): string {
    //note MIDI en notation Korg : 0 = C-1, 60 = C4, 127 = G9
        if ($note === null) {return "";}
        return ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"][$note % 12] . (intdiv($note, 12) - 1);
    }

    private static function cle(string $nom): string {
    //comparaison des noms comme MySQL : sans majuscules ni espaces de fin
        return mb_strtolower(rtrim($nom));
    }

    public static function exportsKronos(): array {
    //dernier export importé de chaque Kronos : 'K2' => idFichier, 'K3' => idFichier (absent si aucun)
        $exports = [];
        foreach (Model::mdRequeteLister(
                "SELECT f.id, n.nom AS typologie FROM " . Fichier::TABLE . " f"
              . " JOIN " . Nomenclature::TABLE . " n ON n.id = f.typologie"
              . " WHERE n.groupe = ? AND n.nom LIKE ? AND f.importContenu = 1 AND f.exclus = 0"
              . " ORDER BY f.dateFichier DESC, f.id DESC",
                [Fichier::GROUPE_TYPOLOGIE, self::PREFIXE_KRONOS . "%"]) as $ligne) {
            $machine = strtoupper(substr((string)$ligne['typologie'], strlen(self::PREFIXE_KRONOS)));
            $exports[$machine] ??= (int)$ligne['id'];
        }
        ksort($exports);
        return $exports;
    }
}
