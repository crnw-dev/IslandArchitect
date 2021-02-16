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
	utils\TextFormat as TF
};
use pocketmine\nbt\tag\{
	ListTag,
	ShortTag,
	CompoundTag
};

use function explode;
use function array_diff;

class RandomGeneration {

	private $blocks = [];

	public function increaseElementChance(int $id, int $meta = 0, int $chance = 1) : bool {
		if (($this->blocks[$id . ':' . $meta] ?? 0) + $chance > 32767) return false;
		if (!isset($this->blocks[$id . ':' . $meta])) $this->blocks[$id . ':' . $meta] = 0;
		$this->blocks[$id . ':' . $meta] += $chance;
		return true;
	}

	/**
	 * @param int|null $chance Null to set the chance to 0
	 * @return bool
	 */
	public function decreaseElementChance(int $id, int $meta = 0, ?int $chance = null) : bool {
		if (!isset($chance)) {
			unset($this->blocks[$id . ':' . $meta]);
			return true;
		}
		if (($this->blocks[$id . ':' . $meta] ?? 0) - $chance <= 0) return false;
		if (!isset($this->blocks[$id . ':' . $meta])) $this->blocks[$id . ':' . $meta] = 0;
		$this->blocks[$id . ':' . $meta] -= $chance;
		return true;
	}

	/**
	 * @return int[]
	 */
	public function getAllElements() : array {
		foreach ($this->blocks as $block => $chance) $blocks[$block] = $chance;
		return $blocks ?? [];
	}

	public function getElementChance(int $id, int $meta = 0) : int {
		return $this->getAllElements()[$id . ':' . $meta] ?? 0;
	}

	public function getTotalChance() : int {
		static $blocks = [];
		static $chance = 0;
		if (array_diff($blocks, $this->blocks)) {
			$blocks = $this->blocks;
			$chance = 0;
			foreach ($this->blocks as $elementchance) $chance += (int)$elementchance;
		}
		return $chance;
	}

	public function randomElementItem(Random $random) : Item {
		$blocks = $this->getAllElements();

		// Random crap "proportional random algorithm" code copied from my old plugin
		$upperl = -1;
		foreach ($blocks as $block => $chance) $upperl += $chance;
		if ($upperl < 0) return Item::get(Item::AIR);
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

	public function equals(RandomGeneration $regex) : bool {
		$blocks = $regex->getAllElements();
		foreach ($this->getAllElements() as $block => $chance) {
			if (($blocks[$block] ?? null) !== $chance) return false;
			unset($blocks[$block]);
		}
		if (!empty($blocks)) return false;
		return true;
	}

	public function getRandomGenerationItem(int $count = 64) : Item {
		foreach ($this->getAllElements() as $block => $chance) {
			$block = explode(':', $block);
			$regex[] = new CompoundTag('', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)($block[1] ?? 0)),
				new ShortTag('chance', (int)$chance)
			]);
			$bi = Item::get((int)$block[0], (int)($block[1] ?? 0));
			$blockslore[] = $bi->getName() . ' (' . $bi->getId() . ':' . $bi->getDamage() . '): ' . TF::BOLD . TF::GREEN . $chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)';
		}
		$i = Item::get(Item::CYAN_GLAZED_TERRACOTTA, 0, $count);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . 'Random generation' . (!empty($blockslore ?? []) ? ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $blockslore ?? []) : ''));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new CompoundTag('random-generation', [
			new ListTag('regex', $regex ?? [])
		])]));
		return $i;
	}
}