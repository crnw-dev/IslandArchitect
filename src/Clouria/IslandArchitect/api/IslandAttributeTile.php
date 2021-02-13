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
	Player,
	item\Item,
	math\Vector3,
	tile\Tile,
	nbt\tag\CompoundTag
};
use Clouria\IslandArchitect\api\RandomGeneration;

class IslandAttributeTile extends Tile {

	public const ATTRIB_RANDOM_GENERATION = 'random-generation';
	public const ATTRIB_WORLD_SPAWN = 'world-spawn';
	public const ATTRIB_ISLAND_CHEST = 'island-chest';

	private $nbt = null;

	public function hasAttribute(string $attribute) : bool {
		$tag = $nbt->getTag($attribute);
		if ($tag === null) return false;
		if ($tag instanceof ByteTag) return (bool)$tag->getValue();
		return true;
	}

	public function setRandomGeneration(ListTag $regex) : void {
		$regex = clone $regex;
		$regex->setName('regex');
		$nbt->setTag(new CompoundTag(self::ATTRIB_RANDOM_GENERATION, [$regex]));
	}

	public function setSpawnPosition() : void {
		$this->nbt->setByte(self::ATTRIB_WORLD_SPAWN, 1);
	}

	public function setChestPosition() : void {
		$this->nbt->setByte(self::ATTRIB_ISLAND_CHEST, 1);
	}

	public function getRandomGeneration() : ?RandomGeneration {
		if (!$this->hasAttribute(self::ATTRIB_RANDOM_GENERATION)) return null;
		return RandomGeneration::fromNBT($this->nbt->getTag(self::ATTRIB_RANDOM_GENERATION)->getListTag('regex'));
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$this->nbt = $nbt->getTag('IslandArchitect') ?? new CompoundTag('IslandArchitect');
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setTag($this->nbt);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void {
		$nbt->setTag(new CompoundTag('IslandArchitect', []));
	}

}