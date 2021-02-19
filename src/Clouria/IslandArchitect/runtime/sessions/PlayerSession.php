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
namespace Clouria\IslandArchitect\runtime\sessions;

use pocketmine\{
	Server,
	Player,
	math\Vector3,
	item\Item,
	utils\TextFormat as TF,
	event\block\BlockPlaceEvent,
	level\format\Chunk
};
use pocketmine\nbt\tag\{
	CompoundTag,
	IntTag,
	ListTag
};

use Clouria\IslandArchitect\{
	IslandArchitect,
	runtime\TemplateIsland,
	runtime\RandomGeneration
};

use function spl_object_id;
use function time;
use function microtime;
use function round;
use function asort;

use const SORT_NUMERIC;

class PlayerSession {

	/**
	 * @var Player
	 */
	private $player;

	public function __construct(Player $player) {
		$this->player = $player;
	}

	public function getPlayer() : Player {
		return $this->player;
	}

	/**
	 * @var TemplateIsland|null
	 */
	protected $island = null;

	public function checkOutIsland(TemplateIsland $island) : void {
		if ($this->export_lock) $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'An island is exporting in background, please wait untol the island export is finished!');
		else $this->island = $island;
	}

	public function getIsland() : ?TemplateIsland {
		return $this->island;
	}

	public function onBlockBreak(Vector3 $vec) : void {
		if ($this->getIsland() === null) return;
		if (($r = $this->getIsland()->getRandomByVector3($vec)) === null) return;
		$this->getPlayer()->sendPopup(TF::BOLD . TF::YELLOW . 'You have destroyed a random generation block, ' . TF::GOLD . 'the item has returned to your inventory!');
		$i = $this->getIsland()->getRandomById($r)->getRandomGenerationItem($this->getIsland()->getRandomSymbolic($r));
		$i->setCount(64);
		$this->getPlayer()->getInventory()->addItem($i);
	}

	public function onBlockPlace(BlockPlaceEvent $ev) : void {
		$item = $ev->getItem();
		if (($nbt = $item->getNamedTagEntry('IslandArchitect')) === null) return;
		if (($nbt = $nbt->getTag('random-generation', CompoundTag::class)) === null) return;
		if (($regex = $nbt->getTag('regex', ListTag::class)) === null) return;
		$regex = RandomGeneration::fromNBT($regex);
		if (
			($regexid = $nbt->getTag('regexid', IntTag::class)) === null or
			($r = $this->getIsland()->getRandomById($regexid = $regexid->getValue())) === null or
			!$r->equals($regex)
		) $regexid = $this->getIsland()->addRandom($r = $regex);
		$this->getIsland()->setBlockRandom($ev->getBlock()->asVector3(), $regexid);
		$symbolic = $this->getIsland()->getRandomSymbolic($regexid);
		$item = clone $item;
		if (!$item->equals($symbolic, true, false)) {
			$nbt = $item->getNamedTag();
			$item = $symbolic;
			foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
			$ev->setCancelled();
			$ev->getBlock()->getLevel()->setBlock($ev->getBlock()->asVector3(), $item->getBlock());
		}
		$item->setCount(64);
		$this->getPlayer()->getInventory()->setItemInHand($item);
	}

	/**
	 * @var bool
	 */
	protected $interact_lock = false;

	public function onPlayerInteract(Vector3 $vec) : void {
		if ($this->interact_lock) return;
		if ($this->getIsland() === null) return;
		if ($this->getPlayer()->isSneaking()) return;
		if (($r = $this->getIsland()->getRandomByVector3($vec)) === null) return;
		$this->interact_lock = true;
		new InvMenuSession($this, $r, function() : void {
			$this->interact_lock = false;
		});
	}

	public function close() : void {
		$this->saveIsland();
	}

	/**
	 * @var bool
	 */
	protected $save_lock = false;

	public function saveIsland() : void {
		if ($this->save_lock) return;
		if (($island = $this->getIsland()) === null) return;
		if (!$island->hasChanges()) return;
		$this->save_lock = true;
		$time = microtime(true);
		IslandArchitect::getLogger()->debug('Saving island "' . $island->getName() . '" (' . spl_object_id($island) . ')');
		$task = new IslandDataEmitTask($island, [], function() use ($island) : void {
			$this->save_lock = false;
			IslandArchitect::getLogger()->debug('Island "' . $island->getName() . '" (' . spl_object_id($island) . ') save completed (' . round(microtime(true) - $time, 2) . 's)');
		});
		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

	/**
	 * @var bool
	 */
	private $export_lock = false;

	public function exportIsland() : void {
		if (($island = $this->getIsland()) === null) return;
		if (!$island->readyToExport()) return;
		$this->export_lock = true;
		$this->island = null;

		$sc = $this->getStartCoord();
		$ec = $this->getEndCoord();
		$xl = [$sc->getFloorX(), $ec->getFloorX()];
		$zl = [$sc->getFloorZ(), $ec->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);

		for ($x=$xl[0] >> 4; $x <= $xl[1]; $x++) for ($z=$zl[0] >> 4; $z <= $zl[1]; $z++) {
			$chunk = $island->getLevel()->getChunk($x, $z);
			if ($chunk === null) $this->missingchunks++;
			else $chunks[] = $chunk;
		}
		$this->chunkqueue = $chunks ?? [];
		if ($this->missingchunks <= 0) $this->startExport();
	}

	/**
	 * @var Chunk[]|null
	 */
	protected $chunkqueue = [];

	/**
	 * @var int
	 */
	protected $missingchunks = 0;

	protected function startExport() : void {
		$task = new IslandDataEmitTask($island, $this->chunkqueue, function() use ($island) : void {
			$this->export_lock = false;
		});
		$this->chunkqueue = null;
		$this->missingchunks = 0;
		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

	public function onChunkLoad(Chunk $chunk) : void {
		if (!$this->export_lock) return;
		if ($this->chunkqueue === null) return;

		$sc = $this->getStartCoord();
		$ec = $this->getEndCoord();
		$xl = [$sc->getFloorX(), $ec->getFloorX()];
		$zl = [$sc->getFloorZ(), $ec->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);

		if (!(
			($chunk->getX() >= ($xl[0] >> 4)) and
			($chunk->getX() <= ($xl[1] >> 4)) and
			($chunk->getZ() >= ($zl[0] >> 4)) and
			($chunk->getZ() <= ($zl[1] >> 4))
		)) return;

		$this->chunkqueue[] = $chunk;
		if (--$this->missingchunks <= 0) $this->exportChunk();
	}

	/**
	 * @param PlayerSession|null $island
	 * @return bool true = error triggered
	 */
	public static function errorCheckOutRequired(Player $player, $session) : bool {
		if ($session !== null and $session->getIsland() !== null) return false;
		$player->sendMessage(TF::BOLD . TF::RED . 'Please check out an island first!');
		return true;
	}
}