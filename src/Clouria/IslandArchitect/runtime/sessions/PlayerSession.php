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
	level\format\Chunk,
	level\Level,
	scheduler\ClosureTask
};
use pocketmine\nbt\tag\{
	CompoundTag,
	IntTag,
	ListTag
};

use Clouria\IslandArchitect\{
	IslandArchitect,
	runtime\TemplateIsland,
	runtime\RandomGeneration,
	conversion\IslandDataLoadTask,
	conversion\IslandDataEmitTask
};

use function spl_object_id;
use function time;
use function microtime;
use function round;
use function asort;
use function get_class;

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
		IslandArchitect::getInstance()->getLogger()->debug('Saving island "' . $island->getName() . '" (' . spl_object_id($island) . ')');
		$task = new IslandDataEmitTask($island, [], function() use ($island, $time) : void {
			$this->save_lock = false;
			IslandArchitect::getInstance()->getLogger()->debug('Island "' . $island->getName() . '" (' . spl_object_id($island) . ') save completed (' . round(microtime(true) - $time, 2) . 's)');
			$island->noMoreChanges();
		});
		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

	/**
	 * @var bool
	 */
	private $export_lock = false;

	public function exportIsland() : void {
		if (($island = $this->getIsland()) === null) return;
		if (!$island->readyToExport()) {
			$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Please set the island start and end coordinate first!');
			return;
		}
		$this->export_lock = true;
		$this->export_island = $island;
		$this->island = null;
		$this->getPlayer()->sendMessage(TF::YELLOW . 'Queued export task for island "' . $island->getName() . '"...');

		$sc = $island->getStartCoord();
		$ec = $island->getEndCoord();
		$xl = [$sc->getFloorX(), $ec->getFloorX()];
		$zl = [$sc->getFloorZ(), $ec->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);
                $xl = array_values($xl); $zl = array_values($zl);
		for ($x=$xl[0] >> 4; $x <= ($xl[1] >> 4); $x++) for ($z=$zl[0] >> 4; $z <= ($zl[1] >> 4); $z++) {
			while (($level = Server::getInstance()->getLevelByName($island->getLevel())) === null) {
				if ($wlock ?? false) {
					$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Island world (' . $island->getLevel() . ') is missing!');
					$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Export task aborted.');
					$this->export_lock = false;
					return;
				}
				Server::getInstance()->loadLevel($island->getLevel());
				$wlock = true;
			}
			$chunk = $level->getChunk($x, $z, true);
			if ($chunk === null) $this->getPlayer()->sendMessage(TF::BOLD . TF::YELLOW . 'Warning: ' . TF::RED . 'Failed to load required chunk ' . $x . ', ' . $z);
			else {
				$chunks[0][$hash = Level::chunkHash($x, $z)] = $chunk->fastSerialize();
				$chunks[1][$hash] = get_class($chunk);
			}
		}
		$this->getPlayer()->sendMessage(TF::GOLD . 'Start exporting...');
		$task = new IslandDataEmitTask($island, $chunks, function() use ($island) : void {
			$this->export_lock = false;
			$this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Export completed!');
		});
		$checker = null;
		$checker = IslandArchitect::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $ct) use (&$checker, $task, &$island) : void {
			if ($task->isCrashed()) {
				$this->export_lock = false;
				$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Critical: Export task crashed' . TF::ITALIC . TF::GRAY . '(The selected region might be too big or an unexpected error occurred)');
				$this->getPlayer()->sendMessage(TF::BOLD . TF::GOLD . 'Attempting to recover original island settings...');
				$this->checkOutIsland($island);
				$restore = new IslandDataEmitTask($island, [], function() : void {
					$this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Restore successed!');
				});
				Server::getInstance()->getAsyncPool()->submitTask($restore);
			}
			if (!$this->export_lock) $checker->cancel();
		}), 10);

		Server::getInstance()->getAsyncPool()->submitTask($task);
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
