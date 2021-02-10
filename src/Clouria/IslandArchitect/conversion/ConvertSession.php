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
	level\Position,
	level\Level,
	math\Vector3
};

class ConvertSession {

	/**
	 * @var Vector3
	 */
	private $pos1;
	
	/**
	 * @var Vector3
	 */
	private $pos2;

	/**
	 * @var Level
	 */
	private $level;

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
	 * @param Position|null $pos The level must be the same one as end coord
	 * @return void
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function startCoord(Position $pos = null) : void {
		$this->validateLevel($pos);
		$this->pos1 = ($pos ?? $this->getPlayer()->asVector3())->asVector3();
	}
	
	/**
	 * @param Position|null $pos The level must be the same one as start coord
	 * @return void
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function endCoord(Position $pos = null) : void {
		$this->validateLevel($pos);
		$this->pos2 = ($pos ?? $this->getPlayer()->asVector3())->asVector3();
	}

	protected function validateLevel(Position $pos) : void {
		if (!isset($this->level)) $this->level = $pos->getLevel();
		elseif ($pos->getLevel() !== $this->level) throw new \InvalidArgumentException('Invalid level instance given');
	}

	public function addRandom(int $id) : void {

	}

}