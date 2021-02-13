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

	private $nbt = null;

	public function getRandomGeneration() : RandomGeneration {
		return RandomGeneration::fromNBT($this->nbt);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$this->nbt = clone $nbt->getListTag('regex');
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setTag($this->nbt);
	}

	protected static function createAdditionalNBT(CompoundTag $nbt, Vector3 $pos, ?int $face = null, ?Item $item = null, ?Player $player = null) : void {
		$nbt->setTag(clone $item->getNamedTagEntry('IslandArchitect')->getCompoundTag('random-generation')->getListTag('regex'));
	}

}