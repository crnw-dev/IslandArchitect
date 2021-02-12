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

use Clouria\IslandArchitect\IslandArchitect;

use pocketmine\{
	item\Item,
	block\Block,
	utils\Random,
	nbt\tag\ListTag
};

use function explode;

class RandomGeneration {

	private $blocks = [];

	public function addBlock(int $id, int $meta = 0, int $chance = 1) : bool {
		if (($this->blocks[$id . ':' . $meta] ?? 0) + $chance > 32767) return false;
		$this->blocks[$id . ':' . $meta] += $chance;
		return true;
	}

	/**
	 * @param int|null $chance Null to set the chance to 0
	 * @return bool
	 */
	public function removeBlock(int $id, int $meta = 0, ?int $chance = null) : bool {
		if (!isset($chance)) {
			unset($this->blocks[$id . ':' . $meta]);
			return true;
		}
		if (($this->blocks[$id . ':' . $meta] ?? 0) - $chance <= 0) return false;
		$this->blocks[$id . ':' . $meta] -= $chance;
		return true;
	}

	/**
	 * @param \pocketmine\block\ItemBlock|Block $item
	 * @param int $chance
	 * @return bool
	 */
	public function addBlockByItem($item, int $chance = 1) : bool {
		if (!$item instanceof Block) $item->getBlock();
		if ($item->getId() === Block::AIR) return false;
		$this->addBlock($item->getId(), $item->getDamage(), $chance);
	}

	/**
	 * @param \pocketmine\block\ItemBlock|Block $item
	 * @param int|null $chance Null to set the chance to 0
	 * @return bool
	 */
	public function removeBlockByItem($item, ?int $chance = null) : bool {
		$this->removeBlock($item->getId(), $item->getDamage(), $chance);
	}

	/**
	 * @return int[]
	 */
	public function getAllRandomBlocks() : array {
		foreach ($this->blocks as $block => $chance) $blocks[$block] = $chance;
		return $blocks ?? [];
	}

	public function randomBlock(Random $random) : Item {
		$blocks = $this->getAllRandomBlocks();

		// Random crap "proportional random algorithm" code copied from my old plugin
		$upperl = -1;
		foreach ($blocks as $block => $chance) $upperl += $chance;
		if ($upperl < 0) break;
		$rand = $random->nextRange(0, $upperl);

		$upperl = -1;
		foreach ($blocks as $block => $chance) {
			$upperl += $chance;
			if (($upperl >= $rand) and ($upperl < ($rand + $chance))) {
				$block = explode(':', $block);
				return Item::get((int)$block[0], (int)($block[1] ?? 0));
			}
		}
	}

	public static function fromNBT(ListTag $nbt) : self {
		$self = new self;
		foreach ($nbt as $block) $self->addBlock($block->getShort('id'), $block->getByte('meta', 0), $block->getShort('chance'));
		return $self;
	}

	public function equals(RandomGeneration $regex) : bool {
		$blocks = $regex->getAllRandomBlocks();
		foreach ($this->getAllRandomBlocks() as $block => $chance) {
			if (!isset($blocks[$block])) return false;
			if ($blocks[$block] != $chance) return false;
		}
		foreach ($blocks = $regex->getAllRandomBlocks() as $block => $chance) {
			if (!isset($this->blocks[$block])) return false;
			if ($this->blocks[$block] != $chance) return false;
		}
		return true;
	}

}