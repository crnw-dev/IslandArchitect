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
	Server,
	level\Position,
	level\Level,
	math\Vector3,
	utils\TextFormat as TF,
	utils\Random,
	event\player\PlayerChatEvent,
	scheduler\TaskHandler,
	scheduler\ClosureTask
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag,
	ByteTag,
	ListTag,
	StringTag
}

use muqsit\invmenu\{
	InvMenu,
	MenuIds,
	InvMenuHandler,
	transaction\DeterministicInvMenuTransaction as InvMenuTransaction
};

use Clouria\IslandArchitect\{
	api\RandomGeneration,
	api\TemplateIslandGenerator
};

use function max;
use function explode;
use function random_int;
use function ceil;
use function count;
use function preg_replace;
use function uniqid;

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

	/**
	 * @var int The positive offset of blocks(chances) display in the inventory, normally 24 as a page since there is 24 available slots.
	 */
	private $invmenu_display = 0;

	/**
	 * @var bool
	 */
	private $invmenu_collapse = false;

	public const INVMENU_ITEM_REMOVE = 0;
	public const INVMENU_ITEM_LUCK = 1;
	public const INVMENU_ITEM_UNLUCK = 2;
	public const INVMENU_ITEM_PREVIOUS = 3;
	public const INVMENU_ITEM_NEXT = 4;
	public const INVMENU_ITEM_SEED = 5;
	public const INVMENU_ITEM_ROLL = 6;
	public const INVMENU_ITEM_SELECTED = 7;
	public const INVMENU_ITEM_COLLAPSE = 8;

	public function editRandom(?int $id = null, ?InvMenu $menu = null, bool $roll_next = true) : void {
		if (isset($this->randoms[$id])) $r = $this->randoms[$id];
		else $id = array_push($this->randoms, $r = new RandomGeneration);
		if (!class_exists(InvMenu::class)) {
			$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Cannot edit regex due to required virion "InvMenu" is not installed. ' . TF::RESET . TF::AQUA . 'A blank regex has been inserted into the island data, ' . TF::YELLOW . 'you can edit the regex after the island is exported!');
			$this->giveRandomGenerationBlock($id);
			return;
		}
		if (!isset($menu)) {
			$m = new InvMenu(MenuIds::TYPE_DOUBLE_CHEST);
			$m->send($this->getPlayer());
			$m->setListener(InvMenu::readonly(function (InvMenuTransaction $transaction) use ($r, $m, $id) : void {
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
						if ($r->getChanceByItem($this->invmenu_selected) >= 32767) return;
						$r->addBlockByItem($this->invmenu_selected);
						$this->editRandom($id, $m)
						break;

					case self::INVMENU_ITEM_UNLUCK:
						$r->removeBlockByItem($this->invmenu_selected, 1);
						break;

					case self::INVMENU_ITEM_PREVIOUS:
						if ($this->invmenu_display <= 0) break;
						$this->invmenu_display -= 24;
						$this->editRandom($id, $m, false);
						break;

					case self::INVMENU_ITEM_NEXT:
						$totalitem = 0;
						if (!$this->invmenu_collapse) foreach ($r->getAllRandomBlocks() as $chance) $totalitem += $chance;
						else $totalitem = count($r->getAllRandomBlocks());
						if ($this->invmenu_display / 24 >= ceil($totalitem / 24)) break;
						$this->invmenu_display += 24;
						$this->editRandom($id, $m, false);
						break;

					case self::INVMENU_ITEM_SEED:
						$this->getPlayer()->sendMessage(TF::YELLOW . 'Pleas enter a seed (Wait 10 second to cancel)');;
						$task = Server::getInstance()->getScheduler()->scheduleDelayedTask(function(int $ct) : void {
							$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'No respond for too long (Timeout)');
							$this->invmenu_seed_lock = null;
						}, 20 * 10);
						$this->invmenu_seed_lock = [$id, $m, $task];
						break;

					case self::INVMENU_ITEM_ROLL:
						$i = $r->randomBlock($this->invmenu_random);
						$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
						$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', -1)]));
						$inv->setItem(44, $i);
						break;

					case self::INVMENU_ITEM_COLLAPSE:
						$this->invmenu_collapse = !$this->invmenu_collapse;
						$this->editRandom($id, $m, false);
						break;

				} else {
					if (!isset($this->invmenu_selected)) $this->invmenu_selected = clone $item;
					else $this->invmenu_selected = null;
					$this->editRandom($id, $m, false);
				}
			}));
			$m->setInventoryCloseListener(function(Player $p, Inventory $inv) use ($id) : void {
				$this->giveRandomGenerationBlock($id);
			});
		}
		$inv = $m->getInventory();
		$m->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $id . (isset($this->invmenu_selected) ? ' (Selected ' . $this->invmenu_selected->getId() : ':' . $this->invmenu_selected->getDamage() . ')'));
		$totalchance = 0;
		foreach ($r->getAllRandomBlocks() as $chance) $totalchance += $chance;
		foreach ($r->getAllRandomBlocks() as $block => $chance) for ($i=0; $i < (!$this->invmenu_collapse ? max((int)$chance, 1) : 1); $i++) {
			if (++$ti <= $this->invmenu_display) continue;
			if (++$ti > ($this->invmenu_display + 24)) continue;
			$block = explode($block);
			$item = Item::get((int)$block[0], (int)($block[1]));
			$selected = false;
			if (isset($this->invmenu_selected)) $selected = $item->equals($this->invmenu_selected);
			if ($selected) $item = Item::get(Item::WOOL, 5);
			$item->setCustomName(TF::RESET . $item->getVanillaName() . "\n" . TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$block[0] . "\n" . TF::RESET . TF::YELLOW . 'Data value (Meta ID): ' . TF::BOLD . TF::GOLD . (int)$block[1] . "\n" . TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)' . "\n\n" . TF::RESET . TF::ITALIC . TF::GRAY . !$selected . '(Click / drop to select this block)' : '(Click / drop again to cancel the select)');
			$item->setNamedTagEntry(new CompoundTag('IslandArchitect', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)$block[1])
			]));
			$inv->setItem($ti - 1, $item, false);
		}
		foreach ([24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 42] as $slot) $inv->setItem($slot, Item::ge, falset(Item::INVISIBLEBEDROCK));

		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a block first)';
		$i = Item::get(Item::CONCRETE, 7);
		$i->setCustomName($prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_REMOVE)]));
		$inv->setItem(36, $i, false);

		/**
		 * @todo Disable this action item if the chance of a block is over or equals 32767
		 */
		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_LUCK)]));
		$inv->setItem(37, $i, false);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Decrease chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_UNLUCK)]));
		$inv->setItem(38, $i, false);

		$i = Item::get($this->invmenu_display > 24 ? Item::EMPTYMAP : Item::PAPER, 0, ceil($this->invmenu_display / 24));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_PREVIOUS)]));
		$inv->setItem(39, $i, false);

		$tdi = !$this->invmenu_collapse ? $totalchance : count($r->getAllRandomBlocks()); // Total display item
		$i = $i = Item::get($tdi / 24 < 1 ? Item::PAPER : Item::EMPTYMAP, max(ceil($tdi / 24) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_NEXT)]));
		$inv->setItem(43, $i, false);

		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)(($this->invmenu_random ?? $this->invmenu_random = new Random(random_int(INT32_MIN, INT32_MAX)))->getSeed()) . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to edit seed or reset random)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_SEED)]));
		$inv->setItem(39, $i, false);

		$i = Item::get(Item::EXPERIENCE_BOTTLE, 0, $roll_next ? ++$this->invmenu_random_rolled_times : $this->invmenu_random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_ROLL)]));
		$inv->setItem(40, $i, false);

		if (!isset($this->invmenu_selected)) {
			$i = Item::get(END_PORTAL);
			$i->setCustomName(TF::GRAY . '(No selected block)');
		} else {
			$i = clone $this->invmenu_selected;
			$i->setCustomName(TF::RESET . TF::YELLOW . 'Selected block: ' . TF::BOLD . TF::GOLD . $i->getVanillaName());
		}
		
		if ($roll_next) {
			$i = $r->randomBlock($this->invmenu_random);
			$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', -1)]));
			$inv->setItem(41, $i, false);
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_SELECTED)]));
		$inv->setItem(34, $i, false);

		$i = Item::get(Item::get(SHULKER_BOX, $this->invmenu_collapse ? 14 : 5));
		$i->setCustomName(TF::RESET . TF::YELLOW . 'Show chance as block (Expand mode): ' . TF::BOLD . ($this->invmenu_collapse ? TF::RED . 'Off' : TF::GREEN . 'On') . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to toggle)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ShortTag('action', self::INVMENU_ITEM_COLLAPSE)]));
		$inv->setItem(43, $i, false);

		$inv->sendContents($inv->getViewers());
	}

	/**
	 * @var array<int, InvMenu, TaskHandler>|null
	 */
	private $invmenu_seed_lock = null;

	/**
	 * @param PlayerChatEvent $ev 
	 * @return void
	 * 
	 * @ignoreCancelled
	 */
	public function onPlayerChat(PlayerChatEvent $ev) : void {
		if (!isset($this->invmenu_seed_lock)) return;
		if (!$ev->getPlayer() === $this->getPlayer()) return;
		$ev->setCancelled();
		$this->invmenu_seed_lock[2]->cancel();
		$msg = $ev->getMessage();
		$this->invmenu_random = new Random(empty(preg_replace('/[0-9]+/i', '', $msg)) ? (int)$msg : TemplateIslandGenerator::convertSeed($msg));
		$this->editRandom($this->invmenu_seed_lock[0], $this->invmenu_seed_lock[1]);
		$this->invmenu_seed_lock = null;
	}

	public function giveRandomGenerationBlock(int $id, bool $removeDuplicatedItem = true) : void {
		$inv = $this->getPlayer()->getInventory();
		if ($removeDuplicatedItem) foreach ($inv->getContents() as $index => $i) if (($nbt = $i->getNamedTagEntry('IslandArchitect')) !== null) if (($nbt = $nbt->getCompoundTag('random-generation')) !== null) if ($nbt->getShort('regexid', -1) === $id) $inv->clear($index);
		foreach ($this->randoms[$id]->getAllRandomBlocks() as $block => $chance) {
			$block = explode($block);
			$regex[] = new CompoundTag('', [
				new ShortTag('id', (int)$block[0]);
				new ByteTag('meta', (int)($block[1] ?? 0))
				new ShortTag('chance', (int)$chance)
			]);
		}
		$i = Item::get(Item::CYAN_GLAZED_TERRACOTTA, 0, 64);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . 'Random generation (Regex #' . $id . ')');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new CompoundTag('random-generation', [
			new ShortTag('regexid', $id),
			new StringTag('uniqueid', uniqid('')),
			new ListTag('regex', $regex ?? [])
			// Saves the regex so when users are sharing the random generation block with others, the block will still valid
		])]));
		$inv->addItem($i);
	}

}