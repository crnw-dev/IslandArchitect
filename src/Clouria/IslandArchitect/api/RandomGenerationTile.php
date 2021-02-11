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
	tile\Tile,
	nbt\tag\ListTag
};
use Clouria\IslandArchitect\api\RandomGeneration;

class RandomGenerationTile extends Tile {

	private $nbt = null;

	public function getRandomGeneration() : RandomGeneration {
		return RandomGeneration::fromNBT($this->nbt);
	}

	public function setNBT(ListTag $nbt) : void {
		$this->nbt = clone $nbt;
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		$this->nbt = clone $nbt;
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setTag($this->nbt);
	}

}