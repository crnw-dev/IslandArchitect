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
namespace Clouria\IslandArchitect\conversion;

use pocketmine\{
	Player,
	math\Vector3,
	item\Item,
	utils\TextFormat as TF
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag,
	ListTag
};

use Clouria\IslandArchitect\{
	IslandArchitect,
	api\TemplateIsland,
	api\RandomGeneration
};

use function spl_object_id;
use function time;

class PlayerSession {

	/**
	 * @var Player
	 */
	private $player;

	public function __construct(Player $player) {
		IslandArchitect::getInstance()->getLogger()->debug('Created player session instance (' . spl_object_id($this) . ') for player "' . $player->getName() . '"');
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
		$this->island = $island;
	}

	public function getIsland() : ?TemplateIsland {
		return $this->island;
	}

	public function onBlockBreak(Vector3 $vec) : void {
		if ($this->getIsland() === null) return;
		if (($r = $this->getIsland()->getBlockRandom($vec)) === null) return;
		$this->getPlayer()->sendPopup(TF::BOLD . TF::RED . 'You have destroyed a random generation block, ' . TF::GREEN . 'the item has returned to your inventory!');
		$i = $this->getIsland()->getRandomById($r)->getRandomGenerationItem($r);
		$i->setCount(64);
		$this->getPlayer()->getInventory()->addItem($i);
	}

	public function onBlockPlace(Vector3 $vec, Item $item) : void {
		if (($nbt = $item->getNamedTagEntry('IslandArchitect')) === null) return;
		if (($nbt = $nbt->getTag('random-generation', CompoundTag::class)) === null) return;
		if (($regex = $nbt->getTag('regex', ListTag::class)) === null) return;
		$regex = RandomGeneration::fromNBT($regex);
		if (
			($regexid = $nbt->getTag('regexid', ShortTag::class)) === null or
			($r = $this->getIsland()->getRandomById($regexid = $regexid->getValue())) === null or
			!$r->equals($regex)
		) $regexid = $this->getIsland()->addRandom($r = $regex);
		$this->getIsland()->setBlockRandom($vec, $regexid);
		$symbolic = $this->getIsland()->getRandomSymbolic($regexid);
		$item = clone $item;
		if (!$item->equals($symbolic, true, false)) {
			$nbt = $item->getNamedTag();
			$item = $symbolic;
			foreach ($nbt as $tag) $item->addNamedTagEntry($tag);
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
		$this->interact_lock = true;
		if (($r = $this->getIsland()->getRandomByVector($vec)) === null) return;
		new InvMenuSession($this, $r, function() : void {
			$this->interact_lock = false;
		});
	}

	public function errorCheckOutRequired() : bool {
		if ($this->getIsland() !== null) return false;
		$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Please check out an island first!');
		return true;
	}

	public function __destruct() {
		IslandArchitect::getInstance()->getLogger()->debug('Player session instance (' . spl_object_id($this) . ') of player "' . $this->getPlayer()->getName() . '" has been destructed');
	}

}