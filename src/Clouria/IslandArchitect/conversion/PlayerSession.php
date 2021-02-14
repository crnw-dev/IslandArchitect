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
	utils\TextFormat as TF
};

use Clouria\IslandArchitect\IslandArchitect;

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
	 * @var IslandTemplate|null
	 */
	private $island = null;

	public function checkOutIsland(IslandTemplate $island) : void {
		$this->island = $island;
	}

	public function getIsland() : ?IslandTemplate {
		return $this->island;
	}

	public function onBlockBreak(Vector3 $vec) : void {
		if ($this->getIsland() === null) return;
		if (($r = $this->getIsland()->getBlockRandom($vec)) === null) return;
		$this->getPlayer()->sendPopup(TF::BOLD . TF::RED . 'You have destroyed a random generation block, ' . TF::GREEN . 'the item has returned to your inventory!');
		$this->getPlayer()->getInventory()->addItem($this->getIsland()->getRandomById()->getRandomGenerationItem());
	}

	/**
	 * @var bool
	 */
	private $interact_lock = falses;

	public function onPlayerInteract(Vector3 $vec) : void {
		if ($this->interact_lock) return;
		$this->interact_lock = true;
		if ($this->getIsland() === null) return;
		if (($r = $this->getIsland()->getBlockRandom($vec)) === null) return;
		new InvMenuSession($this, $r, function () : void {
			$this->interact_lock = false;
		});
	}

	public function __destruct() {
		IslandArchitect::getInstance()->getLogger()->debug('Player session instance (' . spl_object_id($this) . ') of player "' . $player->getName() . '" has been destructed');
	}

}