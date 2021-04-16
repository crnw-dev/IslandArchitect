<?php
/*

		  _____     _                 _
		  \_   \___| | __ _ _ __   __| |
		   / /\/ __| |/ _` | '_ \ / _` |
		/\/ /_ \__ \ | (_| | | | | (_| |
		\____/ |___/_|\__,_|_| |_|\__,_|

		   _            _     _ _            _
		  /_\  _ __ ___| |__ (_) |_ ___  ___| |_
		 //_\\| '__/ __| '_ \| | __/ _ \/ __| __|
		/  _  \ | | (__| | | | | ||  __/ (__| |_
		\_/ \_/_|  \___|_| |_|_|\__\___|\___|\__|

		@ClouriaNetwork | Apache License 2.0

		██╗  ██╗    ██╗  ██╗
        ██║  ██║    ██║ ██╔╝    光   時   LIBERATE
        ███████║    █████╔╝     復   代   HONG
        ██╔══██║    ██╔═██╗     香   革   KONG
        ██║  ██║    ██║  ██╗    港   命
        ╚═╝  ╚═╝    ╚═╝  ╚═╝

														*/
declare(strict_types=1);

namespace Clouria\IslandArchitect\generator;

use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use Clouria\IslandArchitect\generator\properties\IslandChest;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use function max;
use function min;
use function count;
use function explode;
use function is_array;
use function in_array;
use function array_push;
use function array_rand;
use function var_export;
use function array_keys;
use function json_decode;
use function json_encode;
use function array_search;
use function array_values;

class TemplateIsland {

    public const DEFAULT_YOFFSET = 60;
    protected const SYMBOLICS = [
        [Item::PURPLE_GLAZED_TERRACOTTA],
        [Item::WHITE_GLAZED_TERRACOTTA],
        [Item::ORANGE_GLAZED_TERRACOTTA],
        [Item::MAGENTA_GLAZED_TERRACOTTA],
        [Item::LIGHT_BLUE_GLAZED_TERRACOTTA],
        [Item::YELLOW_GLAZED_TERRACOTTA],
        [Item::LIME_GLAZED_TERRACOTTA],
        [Item::PINK_GLAZED_TERRACOTTA],
        [Item::GRAY_GLAZED_TERRACOTTA],
        [Item::SILVER_GLAZED_TERRACOTTA],
        [Item::CYAN_GLAZED_TERRACOTTA]
    ];
    public const VERSION = '1.3'; // TODO: Bump version
    /**
     * @var string
     */
    private $name;
    /**
     * @var Vector3|null
     */
    private $startcoord = null;
    /**
     * @var Vector3|null
     */
    private $endcoord = null;
    /**
     * @var Vector3|null
     */
    private $spawn = null;
    /**
     * @var string|null
     */
    private $level = null;
    /**
     * @var int
     */
    private $yoffset = self::DEFAULT_YOFFSET;
    /**
     * @var RandomGeneration[]
     */
    private $randoms = [];
    /**
     * @var array<int, int[]>
     */
    private $symbolic = [];
    /**
     * @var array<string, string>
     */
    protected $structure;
    /**
     * @var bool
     */
    protected $changed = false;
    /**
     * @var array<string, int>
     */
    private $random_blocks = [];
    private $unused_symbolics = self::SYMBOLICS;
    /**
     * @var array<int, string>
     */
    private $random_labels = [];

    /**
     * @var IslandChest[]
     */
    private $chests = [];

    /**
     * @return IslandChest[]
     */
    public function getChests() : array {
        return $this->chests;
    }

    /**
     * @param Vector3 $vec
     * @param IslandChest $chest
     * @return bool false = This island chest position has already been assigned to another position, please clone the instance first
     */
    public function setChest(Vector3 $vec, IslandChest $chest) : bool {
        if (in_array($chest, $this->chests, true)) return false;
        $this->chests[$vec->getFloorX() . ':' . $vec->getFloorY() . ':' . $vec->getFloorZ()] = $chest;
        return true;
    }

    public function getChest(Vector3 $vec) : ?IslandChest {
        return $this->chests[$vec->getFloorX() . ':' . $vec->getFloorY() . ':' . $vec->getFloorZ()] ?? null;
    }

    public function removeChest(Vector3 $vec) : bool {
        $coord = $vec->getFloorX() . ':' . $vec->getFloorY() . ':' . $vec->getFloorZ();
        if (!isset($this->chests[$coord])) return false;
        unset($this->chests[$coord]);
        return true;
    }

    public function __construct(string $name) {
        $this->name = $name;
    }

    public static function load(string $data, ?\Logger $logger = null) : ?TemplateIsland {
        $dataraw = $data;
        $data = json_decode($data, true);
        if ($data === null) {
            if (!isset($logger)) return null;
            $log = $logger;
            $log->critical('Failed to parse island data file (Enable debug log in pocketmine.yml to view the file data), creating a blank island instance instead...');
            $log->debug(var_export($dataraw, true));
            return null;
        }
        if (
            (int)($version = $data['version'] ?? -1) === -1 or
            ((int)$version > (int)self::VERSION) or
            !isset($data['name'])
        ) return null;

        $self = new self($data['name']);
        if (isset($data['level'])) $self->level = $data['level'];
        if (isset($data['startcoord'])) {
            $coord = $data['startcoord'];
            $self->startcoord = new Vector3((int)($coord['x'] ?? $coord[0]), (int)($coord['y'] ?? $coord[1]), (int)($coord['z'] ?? $coord[2]));
        }
        if (isset($data['endcoord'])) {
            $coord = $data['endcoord'];
            $self->endcoord = new Vector3((int)($coord['x'] ?? $coord[0]), (int)($coord['y'] ?? $coord[1]), (int)($coord['z'] ?? $coord[2]));
        }
        if (isset($data['random_blocks']) or isset($data['blocks'])) $self->random_blocks = $data['random_blocks'] ?? $data['blocks'];
        if (isset($data['random_labels']) or isset($data['labels'])) $self->random_labels = $data['random_labels'] ?? $data['labels'];
        if (isset($data['spawn'])) {
            if (!is_array($data['spawn'])) $coord = explode(':', $data['spawn']);
            else $coord = $data['spawn'];
            $self->spawn = new Vector3((int)($coord['x'] ?? $coord[0]), (int)($coord['y'] ?? $coord[1]), (int)($coord['z'] ?? $coord[2]));
        }
        if (isset($data['y_offset']) or isset($data['yoffset'])) $self->yoffset = $data['y_offset'] ?? $data['yoffset'];
        if (isset($data['random_blocks'])) $self->random_blocks = $data['random_blocks'];
        if (isset($data['symbolic'])) {
            $unused_symbolics = self::SYMBOLICS;
            foreach ($data['symbolic'] as $regexid => $symbolic) {
                $symbolic = explode(':', $symbolic);
                $self->symbolic[$regexid] = [(int)$symbolic[0], (int)($symbolic[1] ?? 0)];
                if (($r = array_search([(int)$symbolic[0], (int)($symbolic[1] ?? 0)], $unused_symbolics, true)) !== false) unset($unused_symbolics[$r]);
            }
            $self->unused_symbolics = $unused_symbolics;
        }
        foreach ($data['randoms'] ?? $data['regex'] ?? [] as $regexdata) {
            $regex = new RandomGeneration;
            foreach ($regexdata as $element => $chance) {
                $element = explode(':', $element);
                if ((int)$chance < 1) continue;
                $regex->increaseElementChance((int)$element[0], (int)($element[1] ?? 0), (int)$chance);
            }
            $self->randoms[] = $regex;
        }
        foreach ($data['chests'] ?? $data['island_chests'] ?? [] as $coord => $chest) $self->chests[$coord] = new IslandChest($chest);
        if (isset($data['structure'])) $self->structure = $data['structure'];
        return $self;
    }

    /**
     * @return RandomGeneration[]
     */
    public function getRandoms() : array {
        return $this->randoms;
    }

    public function removeRandomById(int $id) : bool {
        if (!isset($this->randoms[$id])) return false;
        unset($this->randoms[$id]);
        $this->randoms = array_values($this->randoms);
        $blocks = $this->random_blocks;
        foreach ($blocks as $pos => $rid) if ($rid === $id) {
            $remove[] = $pos;
            unset($blocks[$pos]);
        }
        $this->random_blocks = $blocks;
        $labels = $this->random_labels;
        if (isset($labels[$id])) {
            unset($labels[$id]);
            $this->random_labels = array_values($labels);
        }
        $symbolic = $this->symbolic;
        if (isset($symbolic[$id])) {
            unset($symbolic[$id]);
            $this->symbolic = array_values($symbolic);
        }

        while (($level = Server::getInstance()->getLevelByName($this->getLevel())) === null) {
            if ($wlock ?? false) break;
            Server::getInstance()->loadLevel($this->getLevel());
            $wlock = true;
        }
        if ($level !== null) {
            $block = Block::get(Item::AIR);
            foreach ($remove ?? [] as $pos) {
                $pos = explode(':', $pos);
                $level->setBlock(new Vector3((int)$pos[0], (int)$pos[1], (int)$pos[2]), $block);
            }
        }
        $this->changed = true;
        return true;
    }

    public function getLevel() : ?string {
        return $this->level;
    }

    /**
     * @param string $level Folder name of the level
     */
    public function setLevel(string $level) : void {
        $this->level = $level;
        $this->changed = true;
    }

    /**
     * @param RandomGeneration $random
     * @return int The random generation regex ID
     */
    public function addRandom(RandomGeneration $random) : int {
        $this->changed = true;
        return array_push($this->randoms, $random) - 1;
    }

    public function getRegexId(RandomGeneration $random) : ?int {
        foreach ($this->randoms as $i => $sr) if ($sr === $random) return $i;
        return null;
    }

    /**
     * @param Vector3 $block
     * @param int|null $id
     * @see TemplateIsland::getRandomByVector3()
     */
    public function setBlockRandom(Vector3 $block, ?int $id) : void {
        $coord = $block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ();
        if (isset($id)) $this->random_blocks[$coord] = $id;
        else unset($this->random_blocks[$coord]);
        $this->changed = true;
    }

    /**
     * @param Vector3 $block
     * @return int|null
     * @see TemplateIsland::setBlockRandom()
     */
    public function getRandomByVector3(Vector3 $block) : ?int {
        return $this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] ?? null;
    }

    /**
     * @return array<string, int>
     */
    public function getRandomBlocks() : array {
        return $this->random_blocks;
    }

    public function getRandomSymbolicItem(int $regex) : Item {
        if (!isset($this->symbolic[$regex])) {
            if (empty($this->unused_symbolics)) $this->unused_symbolics = self::SYMBOLICS;
            $chosenone = array_rand($this->unused_symbolics);
            $this->symbolic[$regex] = $this->unused_symbolics[$chosenone];
            unset($this->unused_symbolics[$chosenone]);
        }
        return Item::get($this->symbolic[$regex][0], $this->symbolic[$regex][1] ?? 0);
    }

    /**
     * @return array<int, int[]>
     */
    public function getRandomSymbolics() : array {
        return $this->symbolic;
    }

    public function setRandomSymbolic(int $regex, int $id, int $meta = 0) : void {
        if (isset($this->symbolic[$regex])) $this->unused_symbolics[] = $this->symbolic[$regex];
        $this->symbolic[$regex] = [$id, $meta];
        $this->changed = true;
    }

    public function getRandomLabel(int $regex, bool $nullable = false) : ?string {
        return $this->random_labels[$regex] ?? ($nullable ? null : 'Regex #' . $regex);
    }

    public function setRandomLabel(int $regex, string $label) : void {
        $this->random_labels[$regex] = $label;
    }

    public function resetRandomLabel(int $regex) : bool {
        if (!isset($this->random_labels[$regex])) return false;
        unset($this->random_labels[$regex]);
        $this->changed = true;
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getRandomLabels() : array {
        return $this->random_labels;
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $z
     * @param Random $random
     * @return array|null null = Block is air (The returned block ID is not limited in valid block range 0-255, valid block meta 0-15, also not casted to int)
     */
    public function getProcessedBlock(int $x, int $y, int $z, Random $random) : ?array {
        $y -= $this->getYOffset();
        $block = $this->structure[$x . ':' . $y . ':' . $z] ?? null;
        $block = explode(':', $block);
        if (!isset($block[1])) return null;
        switch ((int)$block[0]) {
            case 1:
                $block = $this->getRandomById((int)$block[1])->randomElementArray($random);
                if ($block[0] === Item::AIR) return null;
                else return $block;
        }
        return null;
    }

    public function getYOffset() : int {
        return $this->yoffset;
    }

    public function setYOffset(?int $yoffset) : void {
        $this->yoffset = $yoffset ?? self::DEFAULT_YOFFSET;
    }

    public function getRandomById(int $id) : ?RandomGeneration {
        return $this->randoms[$id] ?? null;
    }

    public function save() : string {
        $data['level'] = $this->getLevel();
        $data['startcoord'] = $this->getStartCoord() === null ? null : $this->getStartCoord()->floor();
        $data['endcoord'] = $this->getEndCoord() === null ? null : $this->getEndCoord()->floor();
        $data['y_offset'] = $this->getYOffset();
        if (($vec = $this->getSpawn()) !== null) $data['spawn'] = $vec->getFloorX() . ':' . $vec->getFloorY() . ':' . $vec->getFloorZ();
        else $data['spawn'] = null;
        $data['random_blocks'] = $this->random_blocks;
        $data['random_labels'] = $this->random_labels;
        $data['symbolic'] = [];
        foreach ($this->symbolic as $regexid => $symbolic) {
            $symbolic = $symbolic[0] . (isset($symbolic[1]) ? ':' . $symbolic[1] : '');
            $data['symbolic'][$regexid] = $symbolic;
        }
        $data['randoms'] = [];
        foreach ($this->randoms as $regexid => $random) {
            $elements = $random->getAllElements();
            if (empty($elements)) $data['randoms'][$regexid] = ['blockid:meta' => 'chance'];
            else $data['randoms'][$regexid] = $elements;
        }
        foreach ($this->chests as $coord => $chest) $data['chests'][$coord] = $chest->getContents();

        return $this->encode($data);
    }

    public function getStartCoord() : ?Vector3 {
        return $this->startcoord;
    }

    public function setStartCoord(?Vector3 $pos) : void {
        if (isset($pos)) $this->startcoord = $pos->asVector3();
        else $this->startcoord = null;
        $this->changed = true;
    }

    public function getEndCoord() : ?Vector3 {
        return $this->endcoord;
    }

    public function setEndCoord(?Vector3 $pos) : void {
        if (isset($pos)) $this->endcoord = $pos->asVector3();
        else $this->endcoord = null;
        $this->changed = true;
    }

    public function getSpawn() : ?Vector3 {
        return $this->spawn;
    }

    public function setSpawn(?Vector3 $pos) : void {
        if (isset($pos)) $this->spawn = $pos->asVector3();
        else $this->spawn = null;
        $this->changed = true;
    }

    protected function encode(array $data) : string {
        $data['version'] = self::VERSION;
        $data['name'] = $this->getName();

        return json_encode($data);
    }

    public function getName() : string {
        return $this->name;
    }

    /**
     * @param array $chunks
     * @return string JSON encoded template island data
     */
    public function export(array $chunks) : string {
        $sc = $this->getStartCoord()->floor();
        $ec = $this->getEndCoord()->floor();
        $ux = max($sc->getFloorX(), $ec->getFloorX());
        $lx = min($sc->getFloorX(), $ec->getFloorX());
        $uz = max($sc->getFloorZ(), $ec->getFloorZ());
        $lz = min($sc->getFloorZ(), $ec->getFloorZ());
        $uy = max($sc->getFloorY(), $ec->getFloorY());
        $ly = min($sc->getFloorY(), $ec->getFloorY());

        $usedrandoms = [];
        foreach ($chunks[0] as $hash => $chunk) {
            $chunk = $chunks[1][$hash]::fastDeserialize($chunk);
            for ($x = 0; $x < 16; $x++) for ($z = 0; $z < 16; $z++) {
                $wx = ($chunk->getX() << 4) + $x;
                $wz = ($chunk->getZ() << 4) + $z;
                if (
                    $wx < $lx or
                    $wx > $ux or
                    $wz < $lz or
                    $wz > $uz
                ) continue;
                $bx = $wx - $lx;
                $bz = $wz - $lz;
                for ($y = $ly; $y <= $uy; $y++) {
                    if (($id = $chunk->getBlockId($x, $y, $z)) === Block::AIR) continue;
                    $by = $y - $ly;
                    $coord = $wx . ':' . $y . ':' . $wz;
                    $bcoord = $bx . ':' . $by . ':' . $bz;
                    if (isset($this->random_blocks[$coord]) and count($this->randoms[$this->random_blocks[$coord]]->getAllElements()) > 1) {
                        $id = $this->random_blocks[$coord];
                        if (($r = $this->getRandomById($this->random_blocks[$coord])) === null) continue;
                        if (!$r->isValid()) continue;
                        if (($i = array_search($id, $usedrandoms, true)) === false) $id = array_push($usedrandoms, $id) - 1;
                        else $id = $usedrandoms[$i];
                        $data['structure'][$bcoord] = '1:' . $id;
                    } else {
                        $data['structure'][$bcoord] = '0:' . (isset($this->random_blocks[$coord]) ? array_keys($this->randoms[$this->random_blocks[$coord]]->getAllElements())[0] : $id);
                        $meta = $chunk->getBlockData($x & 0x0f, $y, $z & 0x0f);
                        if ($meta !== Item::AIR) $data['structure'][$bcoord] .= ':' . $meta; // Lmao I didn't found this error for like 7 versions
                    }

                    if (isset($this->chests[$coord])) $data['chests'][$bcoord] = $this->chests[$coord];
                }
            }
            unset($chunks[$hash], $wx, $wz, $x, $y, $z);
        }

        foreach ($usedrandoms as $id) {
            $random = $this->randoms[$id] ?? null;
            if (!isset($random)) continue;
            $random->simplifyRegex();
            $data['randoms'][] = $random->getAllElements();
        }

        if (($vec = $this->getSpawn()) !== null) {
            $vec = $vec->subtract($lx, $ly, $lz);
            $coord = $vec->getFloorX() . ':' . $vec->getFloorY() . ':' . $vec->getFloorZ();
            $data['spawn'] = $coord;
        }

        if ($this->yoffset + max($this->getStartCoord()->getFloorY(), $this->getEndCoord()->getFloorY()) > Level::Y_MAX) $this->yoffset = 0;
        if (($yoffset = $this->getYOffset()) > 0) $data['y_offset'] = $yoffset;

        return $this->encode($data ?? []);
    }

    public function dump() : string {
        $data['structure'] = $this->structure;
        foreach ($this->randoms as $random) $data['randoms'][] = $random->getAllElements();
        foreach ($this->chests as $coord => $chest) $data['chests'][$coord] = $chest->getContents();
        if (isset($this->spawn)) $data['spawn'] = $this->spawn->getFloorX() . ':' . $this->spawn->getFloorY() . ':' . $this->spawn->getFloorZ();
        if ($this->yoffset > 0) $data['y_offset'] = $this->yoffset;
        return $this->encode($data ?? []);
    }

    public function noMoreChanges() : void {
        $this->changed = false;
        foreach ($this->randoms as $r) $r->noMoreChanges();
    }

    public function hasChanges() : bool {
        if ($this->changed) return $this->changed;
        foreach ($this->randoms as $r) if ($r->hasChanges()) return true;
        foreach ($this->chests as $c) if ($c->hasChanges()) return true;
        return false;
    }

    public function readyToExport() : bool {
        return (
            isset($this->startcoord) and
            isset($this->endcoord) and
            isset($this->level)
        );
    }

}