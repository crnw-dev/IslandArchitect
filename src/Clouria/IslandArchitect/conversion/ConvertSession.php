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
	math\Vector3,
	utils\TextFormat as TF,
	utils\Random
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag
}

use muqsit\invmenu\{
	InvMenu,
	MenuIds,
	InvMenuHandler,
	transaction\DeterministicInvMenuTransaction
};

use Clouria\IslandArchitect\api\RandomGeneration;

use function max;
use function explode;
use function random_int;
use function ceil;

use const INT32_MIN;
use const INT32_MAX;

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

	/**
	 * @var RandomGeneration[]
	 */
	private $randoms = [];

	/**
	 * @var int
	 */
	private $invmenu_random_rolled_times = 0;

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

	/**
	 * @return RandomGeneration[]
	 */
	public function getRandoms() array {
		return $this->randoms;
	}

	/**
	 * @var Random
	 */
	private $invmenu_random = null;

	public function editRandom(?int $id = null) : void {
		if (isset($this->randoms[$id])) $r = $this->randoms[$id];
		else $r = new RandomGeneration;
		$m = new InvMenu(MenuIds::TYPE_DOUBLE_CHEST);
		$inv = $m->getInventory();
		foreach ($r->getAllRandomBlocks() as $chance) $totalchance += $chance;
		foreach ($r->getAllRandomBlocks() as $block => $chance) for ($i=0; $i < max((int)$chance, 1); $i++) {
			if (++$ti >= 23) continue;
			$item = explode($block);
			$item = Item::get((int)$item[0], (int)($item[1] ?? 0));
			$item->setCustomName(TF::RESET . $item->getVanillaName() . "\n" . TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$item[0] . "\n" . TF::RESET . TF::YELLOW . 'Data value (Meta ID): ' . TF::BOLD . TF::GOLD . (isset($item[1]) ? (int)$item[1] : TF::ITALIC . 'Randomized') . "\n" . TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)');
			$inv->setItem($ti - 1, $item);
		}
		foreach ([23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 41] as $slot) $inv->setItem($slot, Item::get(Item::INVISIBLEBEDROCK));

		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please pick a block first)';
		$i = Item::get(Item::CONCRETE, 7);
		$i->setCustomName($prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_REMOVE)]));
		$inv->setItem(36, $i);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_LUCK)]));
		$inv->setItem(37, $i);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Decrease chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_UNLUCK)]));
		$inv->setItem(38, $i);

		$i = Item::get(Item::PAPER);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_PREVIOUS)]));
		$inv->setItem(39, $i);

		$i = Item::get(Item::EMPTYMAP, max(ceil($totalchance / 23), 64) - 1);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_NEXT)]));
		$inv->setItem(40, $i);

		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)(($this->invmenu_random ?? $this->invmenu_random = new Random(random_int(INT32_MIN, INT32_MAX)))->getSeed()) . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click to edit seed or reset random)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_SEED)]));
		$inv->setItem(42, $i);

		$i = Item::get(Item::EXPERIENCE_BOTTLE, ++$this->invmenu_random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_ROLL)]));
		$inv->setItem(43, $i);

		$i = $r->randomBlock($this->invmenu_random);
		$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_RESULT)]));
		$inv->setItem(44, $i);
	}

}