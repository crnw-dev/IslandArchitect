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
	level\Level,
	block\Block
};

use function explode;
use function array_shift;
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

	/**
	 * @var array<mixed[]>
	 */
	private $data;

	/**
	 * @var array<int, int>[]
	 */
	private $blockdata = null;

	public function __construct(array $islanddata) {
		self::validateData($islanddata);
		$this->data = $islanddata;
	}

	public function locateChunk(Chunk $chunk) : void {
		foreach ($this->data['chunks'][$chunk->getX()][$chunk->getZ()] as $coord => $columnraw) {
			foreach (explode('', $columnraw) as $y => $block) {
				while (count($column ?? []) < $y) $column[] = [Block::AIR, 0];
				$column[] = $this->getBlockFromData($block);
			}
			while (count($column ?? []) <= Level::Y_MAX) $column[] = [Block::AIR, 0];
			foreach ($column ?? [] as $block) $this->blockdata[] = $block;
		}
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
				$fx = $this->getBlockFromData($this->data['functions'][$data], $previous_functions) ?? null;
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
	 * Check if the block ID and data value (meta) is valid
	 * @param array<int, int> $blockdata 
	 * @return array<int, int> Return air, 0 if invalid
	 */
	protected static function validateBlock(array $blockdata) : array {
		if (
			($blockdata[0] >= 0) and
			($blockdata[0] <= 255) and
			(($blockdata[1] ?? 0) >= 0) and
			(($blockdata[1] ?? 0) <= 15)
		) return [(int)$blockdata[0], (int)$blockdata[1]];
		return [Block::AIR, 0];
	}

	/**
	 * @return array<int, int>[]
	 * 
	 * @throws \InvalidStateException
	 */
	public function getBlockData() : array {
		if (!isset($this->blockdata)) throw new \InvalidStateException('Cannot get block data before locate to a chunk');
	}

}