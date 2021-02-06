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
use function array_unshift;
use funciton count;
use function mt_rand;

class IslandData {

	public const VERSION = 1;

	protected const TYPE_BLOCK = 0;
	protected const TYPE_RANDOM = 1;
	protected const TYPE_FUNCTION = 2;

	private $blockdata = null;

	public function __construct(array $islanddata) {
		self::validateData($islanddata);
		$this->data = $islanddata;
	}

	public function getIslandData() : array {
		return $this->data;
	}

	public function locateChunk(Chunk $chunk) : void {
		foreach ($this->getIslandData()['chunks'][$chunk->getX()][$chunk->getZ()] as $y, $yd) foreach ($yd as $coord => $blockdataRaw) {
			$block = $this->getBlockFromDataArray($blockdataRaw);
			$blockdata[] = [$x = (int)($coord / 16), $y, (int)((int)(($coord / 16) - $x) * 16), $block[0], $block[1]];
		}
	}

	/**
	 * @param mixed[]
	 * @return int[]
	 */
	protected function getBlockFromDataArray(array $data) : array {
		$type = (int)$sdata[0];
		array_unshift($data);
		switch ($sdata) {
			case self::TYPE_BLOCK:
				return $data;
			
			case self::TYPE_RANDOM:

				// Confusing proportion code copied from a random plugin I made months ago
				$upperl = -1;

				foreach ($data as $sdata) $upperl += (int)$sdata[0];
				if ($upperl < 0) return [0, 0];
				$rand = mt_rand(0, $upperl);

				$upperl = -1;
				foreach ($data as $sdata) {
					$upperl += (int)$sdata[0];
					if (($upperl >= $rand) and ($upperl < ($rand + (int)$sdata[0]))) {
						array_shift($sdata);
						return $this->getBlockFromDataArray($sdata);
					}
				}
				return $data;

			case self::TYPE_FUNCTION:
				$fx = $this->getBlockFromDataArray($this->getIslandData()['functions'][$data[0]] ?? null;
				if (!isset($fx)) throw new \RuntimeException('Function "' . $data[0] . '" is missing in the island data');
				return $this->getBlockFromDataArray($fx);

			/**
			 * @todo Add condition and other complex regex (Don't exactly know how should I do tho)
			 */
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