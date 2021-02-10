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
	ShortTag,
	ByteTag
}

use muqsit\invmenu\{
	InvMenu,
	MenuIds,
	InvMenuHandler,
	transaction\DeterministicInvMenuTransaction as InvMenuTransaction
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

	/**
	 * @var int
	 */
	private $invmenu_random_rolled_times = 0;

	/**
	 * @var Item|null
	 */
	private $invmenu_selected = null;

	public const INVMENU_ITEM_REMOVE = 0;
	public const INVMENU_ITEM_LUCK = 1;
	public const INVMENU_ITEM_UNLUCK = 2;
	public const INVMENU_ITEM_PREVIOUS = 3;
	public const INVMENU_ITEM_NEXT = 4;
	public const INVMENU_ITEM_SEED = 5;
	public const INVMENU_ITEM_ROLL = 6;

	public function editRandom(?int $id = null, InvMenu $menu = null) : void {
		if (isset($this->randoms[$id])) $r = $this->randoms[$id];
		else $id = array_push($this->randoms, $r = new RandomGeneration);
		if (!isset($menu)) {
			$m = new InvMenu(MenuIds::TYPE_DOUBLE_CHEST);
			$m->send($this->getPlayer());
		}
		$inv = $m->getInventory();
		$m->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $id);
		foreach ($r->getAllRandomBlocks() as $chance) $totalchance += $chance;
		foreach ($r->getAllRandomBlocks() as $block => $chance) for ($i=0; $i < max((int)$chance, 1); $i++) {
			if (++$ti >= 23) continue;
			$block = explode($block);
			$item = Item::get((int)$block[0], (int)($block[1] ?? 0));
			$item->setCustomName(TF::RESET . $item->getVanillaName() . "\n" . TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$block[0] . "\n" . TF::RESET . TF::YELLOW . 'Data value (Meta ID): ' . TF::BOLD . TF::GOLD . (isset($block[1]) ? (int)$block[1] : TF::ITALIC . 'Randomized') . "\n" . TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)');
			$item->setNamedTagEntry(new CompoundTag('IslandArchitect', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)$block[1] ?? -1)
			]));
			$inv->setItem($ti - 1, $item, false);
		}
		foreach ([23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 41] as $slot) $inv->setItem($slot, Item::ge, falset(Item::INVISIBLEBEDROCK));

		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please pick a block first)';
		$i = Item::get(Item::CONCRETE, 7);
		$i->setCustomName($prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_REMOVE)]));
		$inv->setItem(36, $i, false);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_LUCK)]));
		$inv->setItem(37, $i, false);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Decrease chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_UNLUCK)]));
		$inv->setItem(38, $i, false);

		$i = Item::get(Item::PAPER);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_PREVIOUS)]));
		$inv->setItem(39, $i, false);

		$i = Item::get(Item::EMPTYMAP, max(ceil($totalchance / 23), 64) - 1);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_NEXT)]));
		$inv->setItem(40, $i, false);

		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)(($this->invmenu_random ?? $this->invmenu_random = new Random(random_int(INT32_MIN, INT32_MAX)))->getSeed()) . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click to edit seed or reset random)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_SEED)]));
		$inv->setItem(42, $i, false);

		$i = Item::get(Item::EXPERIENCE_BOTTLE, ++$this->invmenu_random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_ROLL)]));
		$inv->setItem(43, $i, false);

		$i = $r->randomBlock($this->invmenu_random);
		$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', -1)]));
		$inv->setItem(44, $i, false);
		$inv->sendContents($inv->getViewers());
		$m->setListener(InvMenu::readonly(function (InvMenuTransaction $transaction) use ($r) : void {
			$in = $transaction->getIn();
			if ($in->getBlock()->getId() !== Item::AIR) {
				$r->addBlockByItem($in, $in->getCount());
				$this->editRandom($id, $m);
				return;
			}
			$nbt = $transaction->getOut()->getNamedTagEntry('IslandArchitect');
			if ($nbt !== null) $nbt = $nbt->getInt('action');
			if ($nbt !== null) switch ($nbt) {
				case self::INVMENU_ITEM_REMOVE:
					if (!isset($this->invmenu_selected)) break;
					$r->removeBlockByItem($this->invmenu_selected);
					$this->invmenu_selected = null;
					$this->editRandom($id, $m);
					return;

				case self::INVMENU_ITEM_LUCK:
					$r->addBlockByItem($this->invmenu_selected);
					$this->editRandom($id, $m)
					break;

				case self::INVMENU_ITEM_UNLUCK:
					$r->removeBlockByItem($this->invmenu_selected, 1);
					break;

				case self::INVMENU_ITEM_PREVIOUS:
					break;

				case self::INVMENU_ITEM_NEXT:
					break;

				case self::INVMENU_ITEM_SEED:
					$transaction->getPlayer()->removeWindow($transaction->getAction()->getInventory());
					$transaction->then(function(Player $player) : void{
						$this->editSeed();
					});
					break;

				case self::INVMENU_ITEM_ROLL:
					$i = $r->randomBlock($this->invmenu_random);
					$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
					$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', -1)]));
					$inv->setItem(44, $i);
					break;

			}
		}));
	}

}