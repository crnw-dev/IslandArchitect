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
namespace Clouria\IslandArchitect\api;

use pocketmine\{
	math\Vector3,
	math\AxisAlignedBB as BB,
	level\Level,
	level\format\Chunk,
	block\Block,
	item\Item
};

use function array_push;
use function implode;
use function explode;
use function asort;
use function in_array;
use function json_encode;
use function array_rand;

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
	 * @var Vector3
	 */
	protected $startcoord = null;

	public function getStartCoord() : ?Vector3 {
		return $this->startcoord;
	}

	public function setStartCoord(Vector3 $pos) : void {
		$this->startcoord = $pos;
	}

	/**
	 * @var Vector3
	 */
	protected $endcoord = null;

	public function getEndCoord() : ?Vector3 {
		return $this->endcoord;
	}

	public function setEndCoord(Vector3 $pos) : void {
		$this->endcoord = $pos;
	}

	/**
	 * @var string
	 */
	protected $level = null;
	
	public function getLevel() : string {
		return $this->level;
	}

	public function setLevel(Level $level) : void {
		$this->level = $level->getFolderName();
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
		return true;
	}

	/**
	 * @param RandomGeneration $random
	 * @return int The random generation regex ID
	 */
	public function addRandom(RandomGeneration $random) : int {
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
		if (($sc = $this->getStartCoord()) === null or ($ec = $this->getEndCoord()) === null) return false;
		$bb = new BB($sc->getX(), $sc->getY() , $sc->getZ(), $ec->getX(), $ec->getY() , $ec->getZ());
		$bb->expand(1.0, 1.0, 1.0);
		if (!$bb->isVectorInside($block)) return false;
		$this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] = $id;
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

	private $unused_symbols = self::SYMBOLICS;

	/**
	 * @todo Allow user to customize symbolic in panel(inventory)
	 */
	public function getRandomSymbolic(int $regex) : Item {
		if (!isset($this->symbolic[$regex])) {
			if (empty($this->unused_symbols)) $this->unused_symbols = self::SYMBOLICS;
			$chosenone = array_rand($this->unused_symbols);
			$this->symbolic[$regex] = $this->unused_symbols[$chosenone];
			unset($this->unused_symbols[$chosenone]);
		}
		return Item::get($this->symbolic[$regex][0], $this->symbolic[$regex][1] ?? 0);
	}

	public function setRandomSymbol(int $regex, int $id, int $meta = 0) : void {
		if (isset($this->symbolic[$regex])) $this->unused_symbols[] = $this->symbolic[$regex];
		$this->symbolic[$regex] = [$id, $meta];
	}

	public const VERSION = 1;

	public function save() : string {
		$data['level'] = $this->getLevel();
		$data['startcoord'] = $this->getStartCoord();
		$data['endcoord'] = $this->getEndCoord();
		foreach ($this->randoms as $random) $data['randoms'][] = $random->getAllElements();

		return $this->encode($data);
	}

	/**
	 * @param Chunk[] $chunks 
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
		foreach ($chunks as $chunk) $chunksmap[$chunk->getX()][$chunk->getZ()] = $chunk;
		for ($x = $xl[0]; $x <= $xl[1]; $x++) for ($z = $zl[0]; $z <= $zl[1]; $z++) {
			$chunk = $chunksmap[$x << 4][$z << 4];
			for ($y = $yl[0]; $y <= $yl[1]; $y++) {
				if (($id = $chunk->getBlockId($x, $y, $z)) === Block::AIR) continue;
				$x -= $xl[0];
				$y -= $yl[0];
				$z -= $zl[0];
				$coord = $x . ':' . $y . ':' . $z . ':';
				if (isset($this->random_blocks[$coord])) {
					$coord = '1:' . ($id = $this->random_blocks[$coord]);
					$usedrandoms[] = $id;
					if (($r = $this->getRandomById($this->random_blocks[$coord])) === null) continue;
					if (!$r->isValid()) continue;
				} else $data['structure'][$coord] = '0:' . $id . ':' . $chunk->getBlockData($x, $y, $z);
			}
		}

		if (!empty($usedrandoms ?? [])) foreach ($this->randoms as $id => $random) if (in_array($id, $usedrandoms)) $data['randoms'][] = $random->getAllElements();

		return $this->encode($data);
	}

	protected function getFilePath() : string {
		return '';
	}

	protected function encode(array $data) : string {
		$data['version'] = self::VERSION;
		$data['name'] = $this->getName();

		return json_encode($data);
	}
}