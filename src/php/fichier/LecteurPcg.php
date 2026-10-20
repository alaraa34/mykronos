<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Lecteur des fichiers PCG du Korg Kronos (OS 1.x, 1.5/1.6, 2.x, 3.x) et de l'OASYS
 *
 * Structure et positions reprises de PCG Tools (C#), (c) 2011-2019 MiKeSoft, Michel Keijzers
 * https://github.com/DaBlick/PCG-Tools  - licence LGPL v3 : merci à l'auteur
 *
 * Le fichier est découpé en blocs (chunks) : nom sur 4 caractères + taille sur 4 octets (big endian)
 *   PRG1 > MBK1/PBK1  banques de Programs     CMB1 > CBK1  banques de Combis (16 timbres)
 *   WSQ1 > WBK1       Wave Sequences          DKT1 > DBK1  Drum Kits
 *   DPI1 > DPN1/DPD1  Drum Patterns           CMB2         banque/program des timbres (OS 1.5/1.6)
 *   GLB1              réglages globaux, dont les noms des catégories (PCG enregistré avec le Global)
 *   SLS1 > SBK1       set lists : 128 listes de 128 emplacements (nom, son pointé, volume, description)
 * Les emplacements vides ou "Init ..." sont écartés (comme PCG Tools).
 *
 * Utilisation : $contenu = LecteurPcg::lire($chemin);
 *   ['modele' => 'Kronos', 'os' => '3.x',
 *    'sons' => [ ['type','banque','nom','categorie','sousCategorie','favori','moteur','tempo'], ...],
 *    'compositions' => [ 'U-B000' => [ ['timbre','banque','canal','noteBasse','noteHaute','velociteBasse','velociteHaute','volume'], ...], ... ],
 *    'setlists' => [ ['numero','nom','slots' => [ ['numero','nom','type','banque','volume','description'], ...]], ...],
 *    'categories' => null (pas de Global) ou ['Program' => [n° => ['libelle' => .., 'sous' => [n° => libellé]]], 'Combi' => [...]] ]
 ******************************************************************************/
class LecteurPcg
{
    //types : mêmes libellés que la nomenclature TYPE (valeurA)
    public const PROGRAM        = "Program";
    public const COMBI          = "Combi";
    public const WAVE_SEQUENCE  = "WaveSequence";
    public const DRUM_KIT       = "DrumKit";
    public const DRUM_PATTERN   = "DrumPattern";

    private const DEBUT_BLOCS       = 0x1C;     //premier bloc après l'en-tête KORG et PCG1
    private const ECART_BLOC        = 12;       //nom + taille + 4 octets
    private const TAILLE_NOM        = 24;
    private const TIMBRES_PAR_COMBI = 16;
    //positions propres à chaque modèle (octet 4 de l'en-tête KORG)
    //  categorieProgram / categorieCombi : octet catégorie (bits 0-4) et sous-catégorie (bits 5-7)
    //  timbres : position des 16 timbres dans une combi, timbre : taille d'un timbre
    private const MODELES = [
    //  favoriProgram / favoriCombi : [octet, bit] (null : pas de favori), tempo : mot de 2 octets (poids faible d'abord) / 100
    //  modeOsc : [octet, masque] du mode d'oscillateur du Program (Single, Double, Drums, EXi, -, Double Drums)
        0x68 => ['nom' => "Kronos", 'categorieProgram' => 2568, 'categorieCombi' => 4790, 'timbres' => 4802, 'timbre' => 188,
                 'favoriProgram' => [2558, 5], 'favoriCombi' => [4791, 0], 'tempo' => 1304, 'modeOsc' => [2558, 0x07]],
        0x70 => ['nom' => "OASYS",  'categorieProgram' => 2556, 'categorieCombi' => 4778, 'timbres' => 4790, 'timbre' => 186,
                 'favoriProgram' => null, 'favoriCombi' => null, 'tempo' => null, 'modeOsc' => [2546, 0x03]],
    ];
    private const NB_PROGRAMS_BANQUE = 128;
    //noms des catégories dans le bloc GLB1 : Programs (18 catégories puis 18 x 8 sous-catégories), puis Combis
    private const GLOBAL_CATEGORIES  = 12912;   //position après l'en-tête du bloc
    private const NB_CATEGORIES      = 18;
    private const NB_SOUS_CATEGORIES = 8;
    private const STATUTS_UTILISES  = [1, 2];   //statut du timbre : 0 Off, 1 Int, 2 Both, 3 Ext, 4 Ex2
    //timbre : position de la zone de clavier et de la plage de vélocité
    private const TIMBRE_NOTE_HAUTE = 37;
    private const TIMBRE_NOTE_BASSE = 38;
    private const TIMBRE_VELO_HAUTE = 40;
    private const TIMBRE_VELO_BASSE = 41;
    //set lists (Kronos) : emplacement = nom (24), type (+24, bits 0-1), banque (+25, bits 0-4), n° (+26),
    //volume (+28), description (+30, 512 caractères)
    private const SLOTS_PAR_SETLIST = 128;
    private const TYPES_SLOT        = [0 => "Combi", 1 => "Program", 2 => "Song"];
    //moteur d'un Program selon son mode d'oscillateur : HD-1 (échantillons) ou EXi (moteurs modélisés : SGX-2, AL-1, CX-3...)
    private const MOTEURS           = [0 => "HD-1 Single", 1 => "HD-1 Double", 2 => "HD-1 Drums", 3 => "EXi", 5 => "HD-1 Double Drums"];
    //timbre : canal MIDI (+2, bits 0-4 : 0-15, 16 = Gch) et volume (+5)
    private const TIMBRE_CANAL      = 2;
    private const TIMBRE_VOLUME     = 5;
    //banque du son appelé par un emplacement de set list (Program) : vérifié sur mes exports K2 / K3
    //0-5 I-A..I-F, 6-12 U-A..U-G (n° de banque), 17-23 U-A..U-G et 24-30 U-AA..U-GG (identifiants PCG)
    private const SLOT_BANQUE_PROGRAM = [0 => "I-A", 1 => "I-B", 2 => "I-C", 3 => "I-D", 4 => "I-E", 5 => "I-F",
                                         6 => "U-A", 7 => "U-B", 8 => "U-C", 9 => "U-D", 10 => "U-E", 11 => "U-F", 12 => "U-G",
                                         17 => "U-A", 18 => "U-B", 19 => "U-C", 20 => "U-D", 21 => "U-E", 22 => "U-F", 23 => "U-G",
                                         24 => "U-AA", 25 => "U-BB", 26 => "U-CC", 27 => "U-DD", 28 => "U-EE", 29 => "U-FF", 30 => "U-GG"];

    //banques (index PCG Tools => nom)
    private const BANQUES_PROGRAM = ["I-A", "I-B", "I-C", "I-D", "I-E", "I-F", "U-A", "U-B", "U-C", "U-D",
                                     "U-E", "U-F", "U-G", "U-AA", "U-BB", "U-CC", "U-DD", "U-EE", "U-FF", "U-GG"];
    private const BANQUES_COMBI   = ["I-A", "I-B", "I-C", "I-D", "I-E", "I-F", "I-G",
                                     "U-A", "U-B", "U-C", "U-D", "U-E", "U-F", "U-G"];
    private const BANQUES_WAVESEQ = ["INT", "U-A", "U-B", "U-C", "U-D", "U-E", "U-F", "U-G",
                                     "U-AA", "U-BB", "U-CC", "U-DD", "U-EE", "U-FF", "U-GG"];
    //banque du program utilisé par un timbre (octet "PcgId") => nom
    private const PCGID_PROGRAM   = [0 => "I-A", 1 => "I-B", 2 => "I-C", 3 => "I-D", 4 => "I-E", 5 => "I-F",
                                     17 => "U-A", 18 => "U-B", 19 => "U-C", 20 => "U-D", 21 => "U-E", 22 => "U-F",
                                     23 => "U-G", 24 => "U-AA", 25 => "U-BB", 26 => "U-CC", 27 => "U-DD",
                                     28 => "U-EE", 29 => "U-FF", 30 => "U-GG",
                                     6 => "GM", 7 => "GM", 8 => "GM", 9 => "GM", 10 => "GM", 11 => "GM", //GM et variations g(1)...g(d)
                                     12 => "GM", 13 => "GM", 14 => "GM", 15 => "GM", 16 => "GM"];
    private const BANQUE_VIRTUELLE = 0x30;      //banques virtuelles V0-A...

    private string $d;                          //contenu binaire
    private int $i = 0;                         //position de lecture
    private string $os = "";
    private array $modele = [];
    private array $sons = [];
    private array $combis = [];                 //[nom banque, n° combi dans la banque, position] pour les timbres
    private array $combisParBanque = [];        //nom banque => nombre de combis (pour CMB2)
    private array $cbk2 = [];                   //position des données CBK2 par banque (OS 1.5/1.6)
    private int $global = 0;                    //position des données du bloc GLB1 (0 : absent)
    private int $sls1 = 0;                      //position du bloc SLS1 (0 : absent)

    public static function lire(string $chemin): array {
        $donnees = @file_get_contents($chemin);
        if ($donnees === false) {
            throw new \RuntimeException("Lecture impossible : " . basename($chemin));
        }
        return (new self($donnees))->analyser();
    }

    private function __construct(string $donnees) {
        $this->d = $donnees;
    }

    private function analyser(): array {
        if (strlen($this->d) < 0x30 || substr($this->d, 0, 4) !== "KORG" || substr($this->d, 16, 4) !== "PCG1") {
            throw new \RuntimeException("Ce n'est pas un fichier PCG Korg");
        }
        $this->modele = self::MODELES[ord($this->d[4])]
                ?? throw new \RuntimeException("Modèle Korg non géré (code " . sprintf("%02X", ord($this->d[4]))
                                              . ", ni Kronos ni OASYS : Nautilus ?)");
        $this->os = $this->modele['nom'] === "OASYS" ? "OASYS"
                  : match (ord($this->d[7])) {0 => "1.x", 1 => "2.x", 2 => "3.x",
                                              default => throw new \RuntimeException("Version d'OS Kronos non gérée")};
        $this->i = self::DEBUT_BLOCS;
        $longueur = strlen($this->d);
        while ($this->i + 8 <= $longueur) {
            $nom = substr($this->d, $this->i, 4);
            $taille = $this->entier($this->i + 4);
            match ($nom) {
                "PRG1"  => $this->lirePrg1($taille),
                "CMB1"  => $this->lireBanques($taille, "CBK1", fn() => $this->lireCbk1()),
                "CMB2"  => $this->lireCmb2($taille),
                "WSQ1"  => $this->lireBanques($taille, "WBK1", fn() => $this->lireWbk1()),
                "DKT1"  => $this->lireBanques($taille, "DBK1", fn() => $this->lireDbk1()),
                "DPI1"  => $this->lireDpi1($taille),
                "INI3"  => $this->ini3($taille),
                "GLB1"  => $this->lireGlb1($taille),
                "SLS1"  => $this->reperer($taille),
                default => $this->i += $taille + self::ECART_BLOC,
            };
        }
        return ['modele' => $this->modele['nom'], 'os' => $this->os,
                'sons' => $this->sons, 'compositions' => $this->compositions(), 'categories' => $this->categories(),
                'setlists' => $this->setlists()];
    }

    //----------------------------------------------------------------------------------------------
    // BLOCS
    //----------------------------------------------------------------------------------------------
    private function ini3(int $taille): void {
        if ($this->os === "1.x") {$this->os = "1.5/1.6";}
        $this->i += $taille + self::ECART_BLOC;
    }

    private function lirePrg1(int $taille): void {
        $this->i += self::ECART_BLOC;
        $debut = $this->i;
        while ($this->i - $debut < $taille) {
            $bloc = substr($this->d, $this->i, 4);
            if ($bloc === "MBK1") {
                $p = $this->i + 4;
                [$nombre, $tailleSon, $idBanque] = [$this->entier($p + 8), $this->entier($p + 12), $this->entier($p + 16)];
                $this->i = $p + 20;
            } elseif ($bloc === "PBK1") {
                $p = $this->i + 12;
                [$nombre, $tailleSon, $idBanque] = [$this->entier($p), $this->entier($p + 4), $this->entier($p + 8)];
                $this->i = $p + 12;
            } else {
                throw new \RuntimeException("Bloc inattendu dans PRG1 : " . $bloc);
            }
            $banque = $this->nomBanqueProgram($idBanque);
            for ($n = 0; $n < $nombre; $n++, $this->i += $tailleSon) {
                $nom = $this->nom($this->i);
                if ($nom === "" || (str_contains($nom, "Init") && str_contains($nom, "Prog"))) {continue;}
                [$octet, $masque] = $this->modele['modeOsc'];
                $this->ajouterSon(self::PROGRAM, $banque, $n, $nom, ord($this->d[$this->i + $this->modele['categorieProgram']]),
                                  ['favori' => $this->bit($this->i, $this->modele['favoriProgram']),
                                   'moteur' => self::MOTEURS[ord($this->d[$this->i + $octet]) & $masque] ?? null]);
            }
        }
    }

    private function lireBanques(int $taille, string $attendu, callable $lireBanque): void {
    //bloc contenant une suite de banques (CMB1/CBK1, WSQ1/WBK1, DKT1/DBK1)
        $this->i += self::ECART_BLOC;
        $debut = $this->i;
        while ($this->i - $debut < $taille) {
            if (substr($this->d, $this->i, 4) !== $attendu) {
                throw new \RuntimeException("Bloc " . $attendu . " attendu");
            }
            $lireBanque();
        }
    }

    private function enteteBanque(): array {
    //en-tête commun CBK1/WBK1/DBK1 : nombre, taille d'un élément, id de banque
        $p = $this->i + 12;
        $this->i = $p + 12;
        return [$this->entier($p), $this->entier($p + 4), $this->entier($p + 8)];
    }

    private function lireCbk1(): void {
        [$nombre, $tailleSon, $idBanque] = $this->enteteBanque();
        $banque = $this->nomBanque(self::BANQUES_COMBI, $idBanque < 0x20000 ? $idBanque : $idBanque - 0x20000 + 7,
                                   $idBanque, 14);
        $this->combisParBanque[$banque] = $nombre;
        for ($n = 0; $n < $nombre; $n++, $this->i += $tailleSon) {
            $nom = $this->nom($this->i);
            if ($nom === "" || (str_contains($nom, "Init") && str_contains($nom, "Combi"))) {continue;}
            $tempo = $this->modele['tempo'] === null ? null
                   : (ord($this->d[$this->i + $this->modele['tempo']]) + 256 * ord($this->d[$this->i + $this->modele['tempo'] + 1])) / 100;
            $this->ajouterSon(self::COMBI, $banque, $n, $nom, ord($this->d[$this->i + $this->modele['categorieCombi']]),
                              ['favori' => $this->bit($this->i, $this->modele['favoriCombi']), 'tempo' => $tempo]);
            $this->combis[] = [$banque, $n, $this->i];
        }
    }

    private function lireCmb2(int $taille): void {
    //OS 1.5/1.6 : une banque CBK2 par banque de combis lue, dans le même ordre
        $this->i += self::ECART_BLOC;
        $debut = $this->i;
        $banques = array_keys($this->combisParBanque);
        $rang = 0;
        while ($this->i - $debut < $taille) {
            if (isset($banques[$rang])) {$this->cbk2[$banques[$rang]] = $this->i + 16;}
            $rang++;
            $this->i += $this->entier($this->i + 4) + self::ECART_BLOC;
        }
    }

    private function lireWbk1(): void {
        [$nombre, $tailleSon, $idBanque] = $this->enteteBanque();
        $banque = $this->nomBanque(self::BANQUES_WAVESEQ, $idBanque < 0x20000 ? $idBanque : $idBanque - 0x20000 + 1, $idBanque);
        for ($n = 0; $n < $nombre; $n++, $this->i += $tailleSon) {
            $nom = $this->nom($this->i);
            if ($nom === "" || $nom === "WaveSequence"
                || (str_contains($nom, "Init") && str_contains($nom, "Wave") && str_contains($nom, "Sequence"))) {continue;}
            $this->ajouterSon(self::WAVE_SEQUENCE, $banque, $n, $nom, null);
        }
    }

    private function lireDbk1(): void {
        [$nombre, $tailleSon, $idBanque] = $this->enteteBanque();
        $banque = $this->nomBanque(["INT", "U-A", "U-B", "U-C", "U-D", "U-E", "U-F", "U-G"],
                                   $idBanque < 0x20000 ? $idBanque : $idBanque - 0x20000 + 1, $idBanque);
        for ($n = 0; $n < $nombre; $n++, $this->i += $tailleSon) {
            $nom = $this->nom($this->i);
            if ($nom === "" || str_starts_with($nom, "Drumkit      0")
                || (str_contains($nom, "Init") && str_contains($nom, "Drum") && str_contains($nom, "Kit"))) {continue;}
            $this->ajouterSon(self::DRUM_KIT, $banque, $n, $nom, null);
        }
    }

    private function lireDpi1(int $taille): void {
    //DPI1 > DPN1 (ignoré) > DPD1 : patterns utilisateur, banque U
        $fin = $this->i + $taille + self::ECART_BLOC;
        $p = $this->i + 12;
        if (substr($this->d, $p, 4) === "DPN1") {
            $p += $this->entier($p + 4) + 12;
        }
        if (substr($this->d, $p, 4) === "DPD1") {
            $tailleDpd1 = $this->entier($p + 4);
            $p += 12;
            $tailleSon = $this->entier($p + 4);
            $nombre = $tailleSon > 0 ? intdiv($tailleDpd1, $tailleSon) : 0;
            $p += 12;
            for ($n = 0; $n < $nombre; $n++, $p += $tailleSon) {
                $nom = $this->nom($p);
                if ($nom === "" || str_starts_with($nom, "DrumPattern      0")
                    || (str_contains($nom, "Init") && str_contains($nom, "Drum") && str_contains($nom, "Pattern"))) {continue;}
                $this->ajouterSon(self::DRUM_PATTERN, "U", $n, $nom, null);
            }
        }
        $this->i = $fin;
    }

    private function reperer(int $taille): void {
    //SLS1 : lu à la fin (set lists), on retient sa position
        $this->sls1 = $this->i;
        $this->i += $taille + self::ECART_BLOC;
    }

    private function lireGlb1(int $taille): void {
        $this->global = $this->i + self::ECART_BLOC;
        $this->i += $taille + self::ECART_BLOC;
    }

    //----------------------------------------------------------------------------------------------
    // CATEGORIES (bloc GLB1)
    //----------------------------------------------------------------------------------------------
    private function categories(): ?array {
        $tailleType = self::NB_CATEGORIES * (self::TAILLE_NOM + self::NB_SOUS_CATEGORIES * self::TAILLE_NOM);
        $debut = $this->global + self::GLOBAL_CATEGORIES;
        if ($this->global === 0 || $debut + 2 * $tailleType > strlen($this->d)) {return null;}
        $categories = [];
        foreach ([self::PROGRAM, self::COMBI] as $rang => $type) {
            $base = $debut + $rang * $tailleType;
            for ($c = 0; $c < self::NB_CATEGORIES; $c++) {
                $sous = [];
                for ($s = 0; $s < self::NB_SOUS_CATEGORIES; $s++) {
                    $sous[$s] = $this->nom($base + self::NB_CATEGORIES * self::TAILLE_NOM
                                           + ($c * self::NB_SOUS_CATEGORIES + $s) * self::TAILLE_NOM);
                }
                $categories[$type][$c] = ['libelle' => $this->nom($base + $c * self::TAILLE_NOM), 'sous' => $sous];
            }
        }
        //contrôle : des noms vides partout signalent un Global illisible
        $noms = array_column($categories[self::PROGRAM], 'libelle');
        return count(array_filter($noms, fn($n) => $n !== "")) >= self::NB_CATEGORIES / 2 ? $categories : null;
    }

    //----------------------------------------------------------------------------------------------
    // SET LISTS (bloc SLS1 > SBK1, Kronos)
    //----------------------------------------------------------------------------------------------
    private function setlists(): array {
    //set lists ayant au moins un emplacement renseigné (nom ou description) ; seuls ces emplacements sont retournés
        if ($this->sls1 === 0 || $this->modele['nom'] !== "Kronos" || $this->os === "1.5/1.6") {return [];}
        $p = $this->sls1 + 16;
        $p += $this->entier($p) + 8;                    //SDB1
        $p += 12;                                       //début STL1
        if (substr($this->d, $p, 4) !== "SBK1") {return [];}
        $p += 12;
        $nombre = $this->entier($p);
        $tailleSlot = $nombre > 0 ? intdiv($this->entier($p + 4), $nombre) : 0;
        $p += 12;
        $setlists = [];
        for ($s = 0; $s < $nombre && $tailleSlot > 0; $s++) {
            $nomListe = $this->nom($p);
            $p += self::TAILLE_NOM;
            $slots = [];
            for ($k = 0; $k < self::SLOTS_PAR_SETLIST; $k++) {
                $o = $p + $k * $tailleSlot;
                $nom = $this->nom($o);
                $description = $this->texte($o + 30, 512);
                if ($nom === "" && $description === "") {continue;}
                $type = self::TYPES_SLOT[ord($this->d[$o + 24]) & 0x03] ?? null;
                $idBanque = ord($this->d[$o + 25]) & 0x1F;
                $numero = ord($this->d[$o + 26]);
                $banque = match ($type) {
                    "Program" => isset(self::SLOT_BANQUE_PROGRAM[$idBanque]) ? self::SLOT_BANQUE_PROGRAM[$idBanque] . sprintf("%03d", $numero) : null,
                    "Combi"   => isset(self::BANQUES_COMBI[$idBanque]) ? self::BANQUES_COMBI[$idBanque] . sprintf("%03d", $numero) : null,
                    default   => null,
                };
                $slots[] = ['numero' => $k, 'nom' => $nom, 'type' => $type, 'banque' => $banque,
                            'volume' => ord($this->d[$o + 28]),
                            'description' => $description];
            }
            $p += self::SLOTS_PAR_SETLIST * $tailleSlot + 16;
            if (count($slots) > 0) {
                $setlists[] = ['numero' => $s, 'nom' => $nomListe, 'slots' => $slots];
            }
        }
        return $setlists;
    }

    //----------------------------------------------------------------------------------------------
    // COMPOSITION DES COMBIS
    //----------------------------------------------------------------------------------------------
    private function compositions(): array {
    //banque combi => timbres actifs (statut Int ou Both, non muets) : n° de timbre (1 à 16), banque du program,
    //canal MIDI (1 à 16, 17 = Gch), zone de clavier (notes MIDI), plage de vélocité et volume
    //un timbre qui pointe vers une banque inconnue ou un n° hors banque (255 = aucun) est ignoré
        $compositions = [];
        foreach ($this->combis as [$banque, $n, $position]) {
            $programs = [];
            for ($t = 0; $t < self::TIMBRES_PAR_COMBI; $t++) {
                $timbre = $position + $this->modele['timbres'] + $t * $this->modele['timbre'];
                $statut = (ord($this->d[$timbre + 2]) >> 5) & 0x07;
                $muet   = (ord($this->d[$timbre + 34]) & 0x80) !== 0;
                if ($muet || !in_array($statut, self::STATUTS_UTILISES, true)) {continue;}
                [$idBanque, $numero] = $this->programDuTimbre($banque, $n, $t, $timbre);
                if (!isset(self::PCGID_PROGRAM[$idBanque]) || $numero >= self::NB_PROGRAMS_BANQUE) {continue;}
                $programs[] = ['timbre'        => $t + 1,
                               'banque'        => self::PCGID_PROGRAM[$idBanque] . sprintf("%03d", $numero),
                               'canal'         => (ord($this->d[$timbre + self::TIMBRE_CANAL]) & 0x1F) + 1,
                               'noteBasse'     => ord($this->d[$timbre + self::TIMBRE_NOTE_BASSE]),
                               'noteHaute'     => ord($this->d[$timbre + self::TIMBRE_NOTE_HAUTE]),
                               'velociteBasse' => ord($this->d[$timbre + self::TIMBRE_VELO_BASSE]),
                               'velociteHaute' => ord($this->d[$timbre + self::TIMBRE_VELO_HAUTE]),
                               'volume'        => ord($this->d[$timbre + self::TIMBRE_VOLUME]) & 0x7F];
            }
            $compositions[$banque . sprintf("%03d", $n)] = $programs;
        }
        return $compositions;
    }

    private function programDuTimbre(string $banque, int $n, int $t, int $timbre): array {
    //[octet banque, n° program] : dans le timbre, ou dans CBK2 pour l'OS 1.5/1.6
        if ($this->os === "1.5/1.6" && isset($this->cbk2[$banque])) {
            $base = $this->cbk2[$banque];
            $nombre = $this->combisParBanque[$banque];
            return [ord($this->d[$base + $n * self::TIMBRES_PAR_COMBI + $t]),
                    ord($this->d[$base + $nombre * self::TIMBRES_PAR_COMBI + $n * self::TIMBRES_PAR_COMBI + $t])];
        }
        return [ord($this->d[$timbre + 1]), ord($this->d[$timbre])];
    }

    //----------------------------------------------------------------------------------------------
    // OUTILS
    //----------------------------------------------------------------------------------------------
    private function ajouterSon(string $type, string $banque, int $n, string $nom, ?int $octetCategorie, array $plus = []): void {
        $this->sons[] = ['type' => $type, 'banque' => $banque . sprintf("%03d", $n), 'nom' => $nom,
                         'categorie'     => $octetCategorie === null ? null : $octetCategorie & 0x1F,
                         'sousCategorie' => $octetCategorie === null ? null : ($octetCategorie >> 5) & 0x07,
                         'favori' => $plus['favori'] ?? false, 'moteur' => $plus['moteur'] ?? null, 'tempo' => $plus['tempo'] ?? null];
    }

    private function bit(int $position, ?array $octetBit): bool {
    //bit [octet, n° de bit] à partir de $position (false si le modèle ne le gère pas)
        return $octetBit !== null && ((ord($this->d[$position + $octetBit[0]]) >> $octetBit[1]) & 1) === 1;
    }

    private function texte(int $position, int $longueur): string {
    //texte libre (description d'emplacement) : jusqu'au premier caractère nul, fins de ligne conservées
        $brut = substr($this->d, $position, $longueur);
        $fin = strpos($brut, "\0");
        $texte = rtrim($fin === false ? $brut : substr($brut, 0, $fin));
        return mb_convert_encoding($texte, "UTF-8", "ISO-8859-1");
    }

    private function nomBanqueProgram(int $id): string {
        if ($id >= self::BANQUE_VIRTUELLE && $id < 0x8000) {
            return "V" . ($id - self::BANQUE_VIRTUELLE);
        }
        $index = $id === 0x8000 ? 5 : ($id < 0x8000 ? $id : $id - 0x20000 + 6);
        return self::BANQUES_PROGRAM[$index] ?? ("?" . dechex($id));
    }

    private function nomBanque(array $noms, int $index, int $id, int $premierVirtuel = -1): string {
        if ($premierVirtuel >= 0 && $id >= self::BANQUE_VIRTUELLE && $id < 0x20000) {
            return "V" . ($id - self::BANQUE_VIRTUELLE);
        }
        return $noms[$index] ?? ("?" . dechex($id));
    }

    private function entier(int $position): int {
        return unpack("N", substr($this->d, $position, 4))[1];
    }

    private function nom(int $position): string {
        $brut = substr($this->d, $position, self::TAILLE_NOM);
        $fin = strpos($brut, "\0");
        $texte = rtrim($fin === false ? $brut : substr($brut, 0, $fin));
        return mb_convert_encoding($texte, "UTF-8", "ISO-8859-1");
    }
}
