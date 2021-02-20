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
														*/

declare(strict_types=1);
namespace Clouria\IslandArchitect\runtime;

use pocketmine\{
	math\Vector3,
	math\AxisAlignedBB as BB,
	level\Level,
	level\format\Chunk,
	block\Block,
	item\Item,
	utils\Random
};

use function array_push;
use function implode;
use function explode;
use function asort;
use function in_array;
use function json_encode;
use function json_decode;
use function array_rand;
use function array_search;

use const SORT_NUMERIC;

class TemplateIsland {

	public function __construct(string $name) {
		$this->name = $name;
	}

	/**
	 * @var string
	 */
	protected $name;

	public function getName() : string {
		return $this->name;
	}

	/**
	 * @var Vector3|null
	 */
	protected $startcoord = null;

	public function getStartCoord() : ?Vector3 {
		return $this->startcoord;
	}

	public function setStartCoord(Vector3 $pos) : void {
		$this->startcoord = $pos->asVector3();
		$this->changed = true;
	}

	/**
	 * @var Vector3|null
	 */
	protected $endcoord = null;

	public function getEndCoord() : ?Vector3 {
		return $this->endcoord;
	}

	public function setEndCoord(Vector3 $pos) : void {
		$this->endcoord = $pos->asVector3();
		$this->changed = true;
	}

	/**
	 * @var string|null
	 */
	protected $level = null;
	
	public function getLevel() : ?string {
		return $this->level;
	}

	public function setLevel(Level $level) : void {
		$this->level = $level->getFolderName();
		$this->changed = true;
	}

	/**
	 * @var RandomGeneration[]
	 */
	protected $randoms = [];

	/**
	 * @return RandomGeneration[]
	 */
	public function getRandoms() : array {
		return $this->randoms;
	}

	public function getRandomById(int $id) : ?RandomGeneration {
		return $this->randoms[$id] ?? null;
	}

	public function removeRandomById(int $id) : bool {
		if (!isset($this->randoms[$id])) return false;
		unset($this->randoms[$id]);
		$this->changed = true;
		return true;
	}

	/**
	 * @param RandomGeneration $random
	 * @return int The random generation regex ID
	 */
	public function addRandom(RandomGeneration $random) : int {
		$this->changed = true;
		return array_push($this->randoms, $random) - 1;
	}

	/**
	 * @var array<string, int>
	 */
	private $random_blocks = [];

	/**
	 * @see TemplateIsland::getRandomByVector3()
	 */
	public function setBlockRandom(Vector3 $block, int $id) : bool {
		if (!isset($this->getRandoms()[$id])) return false;
		$this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] = $id;
		$this->changed = true;
		return true;
	}

	/**
	 * @see TemplateIsland::setBlockRandom()
	 */
	public function getRandomByVector3(Vector3 $block) : ?int {
		return $this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] ?? null;
	}

	/**
	 * @var array<int, int[]>
	 */
	protected $symbolic = [];

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

	private $unused_symbolics = self::SYMBOLICS;

	/**
	 * @todo Allow user to customize symbolic in panel(inventory)
	 */
	public function getRandomSymbolic(int $regex) : Item {
		if (!isset($this->symbolic[$regex])) {
			if (empty($this->unused_symbolics)) $this->unused_symbolics = self::SYMBOLICS;
			$chosenone = array_rand($this->unused_symbolics);
			$this->symbolic[$regex] = $this->unused_symbolics[$chosenone];
			unset($this->unused_symbolics[$chosenone]);
		}
		return Item::get($this->symbolic[$regex][0], $this->symbolic[$regex][1] ?? 0);
	}


	public function setRandomSymbolic(int $regex, int $id, int $meta = 0) : void {
		if (isset($this->symbolic[$regex])) $this->unused_symbolics[] = $this->symbolic[$regex];
		$this->symbolic[$regex] = [$id, $meta];
		$this->changed = true;
	}

	/**
	 * @var array<string, string>
	 */
	protected $structure;

	public function getChunkBlocks(int $cx, int $cz, Random $random) : array {
		for ($z=$cz << 4; $z < ($cz + 1) << 4; $z++) for ($x=$cx << 4; $x < ($cx + 1) << 4; $x++) for ($y=0; $y <= Level::Y_MAX; $y++) {
			$block = $this->structure[$x . ':' . $y . ':' . $z] ?? null;
			if (!isset($block)) continue;
			$block = explode(':', $block);
			if ((int)$block[0] === 0) $blocks[$x][$z][$y] = [(int)$block[1], (int)$block[2]];
			if ((int)$block[0] === 1) $blocks[$x][$z][$y] = $this->randomElementArray((int)$block[1]);
		}
	}

	public const VERSION = 1.0;

	public function save() : string {
		$data['level'] = $this->getLevel();
		$data['startcoord'] = $this->getStartCoord();
		$data['endcoord'] = $this->getEndCoord();
		$data['unused_symbolics'] = $this->unused_symbolics;
		$data['symbolics'] = $this->symbolic;
		foreach ($this->randoms as $random) $data['randoms'][] = $random->getAllElements();

		return $this->encode($data);
	}

	/**
	 * @param mixed[] $chunks 
	 * @return string JSON encoded template island data
	 */
	public function export(array $chunks) : string {
		$sc = $this->getStartCoord();
		$ec = $this->getEndCoord();
		$xl = [$sc->getFloorX(), $ec->getFloorX()];
		$yl = [$sc->getFloorY(), $ec->getFloorY()];
		$zl = [$sc->getFloorZ(), $ec->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($yl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);

		$usedrandoms = [];
		foreach ($chunks[0] as $hash => $chunk) $chunksmap[$hash] = $chunks[1][$hash]::fastDeserialize($chunk);
		for ($x = $xl[0]; $x <= $xl[1]; $x++) for ($z = $zl[0]; $z <= $zl[1]; $z++) {
			$chunk = $chunksmap[Level::chunkHash($x, $z)] ?? null;
			if ($chunk === null) continue;
			for ($y = $yl[0]; $y <= $yl[1]; $y++) {
				if (($id = $chunk->getBlockId(
					$tx = (int)((($x / 16) - (int)($x / 16)) * 16)
					, $y, 
					$tz = (int)((($z / 16) - (int)($z / 16)) * 16)
				)) === Block::AIR) continue;
				$x -= $xl[0];
				$y -= $yl[0];
				$z -= $zl[0];
				$coord = $x . ':' . $y . ':' . $z . ':';
				if (isset($this->random_blocks[$coord])) {
					$id = $this->random_blocks[$coord];
					if (($r = $this->getRandomById($this->random_blocks[$coord])) === null) continue;
					if (!$r->isValid()) continue;
					if (($i = array_search($id, $usedrandoms, true)) === false) $id = array_push($usedrandoms, $id) - 1;
					else $id = $usedrandoms[$i];
					$data['structure'][$coord] = '1:' . $id;
				} else $data['structure'][$coord] = '0:' . $id . ':' . $chunk->getBlockData($tx, $y, $tz);
			}
		}

		if (!empty($usedrandoms ?? [])) foreach ($this->randoms as $id => $random) if (in_array($id, $usedrandoms)) $data['randoms'][] = $random->getAllElements();

		return $this->encode($data);
	}

	protected function encode(array $data) : string {
		$data['version'] = self::VERSION;
		$data['name'] = $this->getName();

		return json_encode($data);
	}

	public static function load(string $data) : ?TemplateIsland {
		$data = json_decode($data, true);
		if ($data === false) return null;
		if (
			(int)($version = $data['version'] ?? -1) === -1 or
			((int)$version > self::VERSION) or
			!isset($data['name'])
		) return null;

		$self = new self($data['name']);
		if (isset($data['level'])) $self->level = $data['level'];
		if (isset($data['startcoord'])) {
			$coord = $data['startcoord'];
			$self->startcoord = new Vector3((int)$coord['x'], (int)$coord['y'], (int)$coord['z']);
		}
		if (isset($data['endcoord'])) {
			$coord = $data['endcoord'];
			$self->endcoord = new Vector3((int)$coord['x'], (int)$coord['y'], (int)$coord['z']);
		}
		if (isset($data['unused_symbolics'])) $self->unused_symbolics = $data['unused_symbolics'];
		if (isset($data['symbolic'])) $self->symbolic = $data['symbolic'];
		foreach ($data['randoms'] ?? [] as $regexdata) {
			$regex = new RandomGeneration;
			foreach ($regexdata as $element => $chance) {
				$element = explode(':', $element);
				$regex->increaseElementChance($element[0], $element[1] ?? 0, $chance);
			}
			$self->randoms[] = $regex;
		}
		if (isset($data['structure'])) $self->structure = $data['structure'];
		return $self;
	}

	/**
	 * @var bool
	 */
	protected $changed = false;

	public function noMoreChanges() : void {
		$this->changed = false;
	}

	public function hasChanges() : bool {
		return $this->changed;
	}

	public function readyToExport() : bool {
		return (
			isset($this->startcoord) and
			isset($this->endcoord) and
			isset($this->level)
		);
	}

}