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
 * Les emplacements vides ou "Init ..." sont écartés (comme PCG Tools).
 *
 * Utilisation : $contenu = LecteurPcg::lire($chemin);
 *   ['modele' => 'Kronos', 'os' => '3.x', 'sons' => [ ['type','banque','nom','categorie','sousCategorie'], ...],
 *    'compositions' => [ 'U-B000' => ['U-AA000', ...], ... ] ]
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
        0x68 => ['nom' => "Kronos", 'categorieProgram' => 2568, 'categorieCombi' => 4790, 'timbres' => 4802, 'timbre' => 188],
        0x70 => ['nom' => "OASYS",  'categorieProgram' => 2556, 'categorieCombi' => 4778, 'timbres' => 4790, 'timbre' => 186],
    ];
    private const NB_PROGRAMS_BANQUE = 128;
    private const STATUTS_UTILISES  = [1, 2];   //statut du timbre : 0 Off, 1 Int, 2 Both, 3 Ext, 4 Ex2

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
                default => $this->i += $taille + self::ECART_BLOC,
            };
        }
        return ['modele' => $this->modele['nom'], 'os' => $this->os,
                'sons' => $this->sons, 'compositions' => $this->compositions()];
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
                $this->ajouterSon(self::PROGRAM, $banque, $n, $nom, ord($this->d[$this->i + $this->modele['categorieProgram']]));
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
            $this->ajouterSon(self::COMBI, $banque, $n, $nom, ord($this->d[$this->i + $this->modele['categorieCombi']]));
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

    //----------------------------------------------------------------------------------------------
    // COMPOSITION DES COMBIS
    //----------------------------------------------------------------------------------------------
    private function compositions(): array {
    //banque combi => banques des programs utilisés par les timbres actifs (statut Int ou Both, non muets), sans doublon
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
                $programs[] = self::PCGID_PROGRAM[$idBanque] . sprintf("%03d", $numero);
            }
            $compositions[$banque . sprintf("%03d", $n)] = array_values(array_unique($programs));
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
    private function ajouterSon(string $type, string $banque, int $n, string $nom, ?int $octetCategorie): void {
        $this->sons[] = ['type' => $type, 'banque' => $banque . sprintf("%03d", $n), 'nom' => $nom,
                         'categorie'     => $octetCategorie === null ? null : $octetCategorie & 0x1F,
                         'sousCategorie' => $octetCategorie === null ? null : ($octetCategorie >> 5) & 0x07];
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
