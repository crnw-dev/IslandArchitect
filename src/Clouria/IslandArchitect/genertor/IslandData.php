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

use pocketmine\{
	level\format\Chunk,
	block\Block
};

use function unserialize;
use function explode;
use function array_unshift;
use funciton count;
use function mt_rand;
use function in_array;
use function explode;
use function is_string;
use function is_int;
use function is_float;
use function is_array;

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
		foreach ($this->getIslandData()['chunks'][$chunk->getX()][$chunk->getZ()] as $coord => $column) foreach (explode('', $column) as $block) $blockdata[] = [$x = (int)($coord / 16), $y, (int)((int)(($coord / 16) - $x) * 16), ($block = $this->getBlockFromData($block))[0] ?? Block::AIR, $block[1] ?? 0];
		$this->blockdata[] = $blockdata ?? [];
	}

	/**
	 * @param string|int|float|array[] $data String for function name, int for block ID, float for block ID + data value (meta), array for other types
	 * @return int[]
	 */
	protected function getBlockFromData($data, array $previous_functions = []) : array {
		switch (true) {
			case is_string($data):
				if (in_array($data, $previous_functions, true)) throw new \RuntimeException('Recurse function detected, running function "' . $data . '" inside itself');
				$previous_functions[] = $data;
				$fx = $this->getBlockFromData($this->getIslandData()['functions'][$data], $previous_functions) ?? null;
				if (!isset($fx)) throw new \RuntimeException('Function "' . $data . '" is missing in the island data');
				return $this->getBlockFromData($fx, $previous_functions);

			case is_int($data):
				return self::validateBlock([$data, 0]);

			case is_float($data):
				$meta = $data - (int)$data;
				return self::validateBlock([(int)($data - $meta), (int)($meta * 100)]);

			case is_array($data):
				$sdata = $data[0];
				array_shift($data);
				switch ($sdata) {
					case self::TYPE_BLOCK:
						return $this->getBlockFromData((int)$data[0] + ((int)$data[1] / 100), $previous_functions);

					case self::TYPE_FUNCTION:
						return $this->getBlockFromData((string)$data[0], $previous_functions);
					
					case self::TYPE_RANDOM:

						// Confusing proportion code copied from a random plugin I made months ago
						$upperl = -1;

						foreach ($data as $sdata) $upperl += (int)$sdata[0];
						if ($upperl < 0) return [Block::AIR, 0];
						$rand = mt_rand(0, $upperl);

						$upperl = -1;
						foreach ($data as $sdata) {
							$upperl += (int)$sdata[0];
							if (($upperl >= $rand) and ($upperl < ($rand + (int)$sdata[0]))) {
								array_shift($sdata);
								return $this->getBlockFromData($sdata, $previous_functions);
							}
						}
						return self:::validateBlock($data);

					/**
					 * @todo Add condition and other complex regex (Don't exactly know how should I do tho)
					 */
				}
				break;

				default:
					return [Block::AIR, 0];
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