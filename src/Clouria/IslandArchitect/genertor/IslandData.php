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
namespace Clouria\IslandArchitect\genertor;

use pocketmine\level\format\Chunk;

use function unserialize;
use function explode;

class IslandData {

	public const VERSION = 1;

	private $blockdata = null;

	public function __construct(array $islanddata) {
		self::validateData($islanddata);
		$this->data = $islanddata;
	}

	public function getIslandData() : array {
		return $this->data;
	}

	public function locateChunk(Chunk $chunk) : void {
		foreach ($this->getIslandData()['chunk'][$chunk->getX()][$chunk->getZ()] as $y, $yd) foreach ($yd as $coord => $blockdataRaw) {
			$blockdata[] = [$coord / 16, $y, ($coord / 16) / 16];
		}
	}

	/**
	 * @return BlockData[]
	 * 
	 * @throws \InvalidStateException
	 */
	public function getBlockData() : array {
		if (!isset($this->blockdata)) throw new \InvalidStateException('Cannot get block data before locate to a chunk');
	}

}