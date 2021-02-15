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
	level\Level,
	item\Item,
	block\Block,
	utils\TextFormat as TF,
	utils\Random,
	inventory\Inventory
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag,
	ByteTag,
	ListTag
};

use muqsit\invmenu\{
	InvMenu,
	transaction\DeterministicInvMenuTransaction as InvMenuTransaction
};
use jojoe77777\FormAPI\CustomForm;

use Clouria\IslandArchitect\{
	IslandArchitect,
	api\RandomGeneration,
	api\IslandAttributeTile,
	api\TemplateIslandGenerator
};

use function max;
use function explode;
use function random_int;
use function ceil;
use function count;
use function preg_replace;
use function time;
use function array_push;
use function class_exists;
use function implode;
use function spl_object_hash;

use const INT32_MIN;
use const INT32_MAX;

class InvMenuSession {

	/**
	 * @var PlayerSession
	 */
	private $session;

	/**
	 * @var RandomGeneration
	 */
	private $regex;

	/**
	 * @var int
	 */
	private $regexid;

	public function __construct(PlayerSession $session, ?int $regexid = null) {
		if ($regex === null) {
			$regex = new RandomGeneration;
			$session->getIsland()->addRandom($regex);
		}
		elseif (($regex = $session->getTemplateIsland()->getRandomById($regexid)) === null) throw new \InvalidArgumentException('Invalid regex ID');
		$this->session = $session;
		$this->regexid = $regexid;
		$this->regex = $regex;

		$this->panelInit();
		$this->menu->send($session->getPlayer());
	}

	public function getRegex() : RandomGeneration {
		return $this->regex;
	}

	public function getRegexId() : int {
		return $this->regexid;
	}

	public function getSession() : PlayerSession {
		return $this->session;
	}

	/**
	 * @var InvMenu
	 */
	protected $menu;

	/**
	 * @var Random
	 */	
	private $random;

	protected function panelInit() : void {
		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->menu->setListener(InvMenu::readonly(\Closure::fromCallable([$this, 'transactionCallback'])));
		$this->menu->setInventoryCloseListener(function(Player $p, Inventory $inv) : void {
			if (!$this->item_lock) $p->getInventory()->addItem($this->getRegex()->getRandomGenerationItem());
		});
		$this->menu->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $this->getRegexId());
		$inv = $this->menu->getInventory();

		$i = Item::get(Item::INVISIBLEBEDROCK);
		$i->setCustomName('');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		foreach ([33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 51] as $slot) $inv->setItem($slot, $i, false);

		$this->random = new Random(random_int(INT32_MIN, INT32_MAX));
		$itemname = TF::RESET . TF::BOLD . TF::GOLD . (int)$this->random->getSeed();
		if (class_exists(CustomForm::class)) {
			$i = Item::get(Item::SEEDS);
			$i->setCustomName($itemname . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to edit seed or reset random)');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_SEED)]));
		} else {
			$i = Item::get(Item::PUMPKIN_SEEDS);
			$i->setCustomName($itemname . "\n\n" . TF::RESET . TF::BOLD . TF::RED . 'Cannot edit seed due to ' . "\n" . 'required virion "FormAPI" is not installed.');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		}
		$inv->setItem(48, $i, false);

		$this->panelSelect();
	}

	protected function panelElementSlotsUpdate() : void {
		for ($i=0; $i < self::PANEL_AVAILABLE_SLOTS_SIZE; $i++) $inv->clear($i, false);
		$totalchance = 0;
		foreach ($r->getAllElements() as $chance) $totalchance += $chance;
		foreach ($r->getAllElements() as $block => $chance) {
			$block = explode(':', $block);
			$item = Item::get((int)$block[0], (int)($block[1]));
			$selected = false;
			if (isset($this->selected)) $selected = $item->equals($this->selected);
			if ($selected) $item = Item::get(Item::WOOL, 5);
			$item->setCustomName(
				TF::RESET . $item->getVanillaName() . "\n" .
				TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$block[0] . "\n" .
				TF::RESET . TF::YELLOW . 'Data value (Meta ID): ' . TF::BOLD . TF::GOLD . (int)$block[1] . "\n" .
				TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)' . "\n\n" .
				TF::RESET . TF::ITALIC . TF::GRAY . (!$selected ? '(Click / drop to select this block)' : '(Click / drop again to cancel the select)'));
			$item->setNamedTagEntry(new CompoundTag('IslandArchitect', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)$block[1])
			]));
			for ($i=0; $i < (!$this->collapse ? max((int)$chance, 1) : 1); $i++) {
				if (!isset($ti)) $ti = 0;
				$cti = ++$ti;
				if ($cti <= $this->display) continue;
				if ($cti > ($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE)) continue;
				$inv->setItem($ti - $this->display - 1, $item, false);
			}
		}
	}

	protected function panelSelect() : void {
		$this->panelElementSlotsUpdate();
		$s = $this->selected !== null;
		if (!$s) {
			$prefix = TF::RESET . TF::BOLD . TF::GRAY;
			$surfix = "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a block first)';
		}

		$i = Item::get(Item::CONCRETE, $s ? 14 : 7);
		$i->setCustomName($s ? TF::RESET . TF::BOLD . TF::RED . 'Remove' : $prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $s ? self::ITEM_REMOVE : -1)]));
		$inv->setItem(45, $i, false);

		$e = $s and ($this->getRegex()->getAllElements()[$this->selected->getId() . ':' . $this->selected->getDamage()] < 32767);
		$i = Item::get($e ? Item::EMERALD_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::GREEN . 'Increase chance' : $prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_LUCK : -1)]));
		$inv->setItem(46, $i, false);

		$e = $s and ($this->getRegex()->getAllElements()[$this->selected->getId() . ':' . $this->selected->getDamage()] > 1);
		$i = Item::get($e ? Item::REDSTONE_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::RED . 'Decrease chance' : $prefix . 'Decrease chance' : $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_UNLUCK : -1)]));
		$inv->setItem(47, $i, false);

	}

	protected function transactionCallback(InvMenuTransaction $transaction) : void {
		$in = $transaction->getIn();
		$out = $transaction->getOut();
		if (
			isset($transaction->getTransaction()->getInventories()[spl_object_hash($this->getPlayer()->getInventory())]) and
			$in->getBlock()->getId() !== Item::AIR
		) {
			$this->getRegex()->increaseElementChance($in->getId(), $in->getDamage(), $in->getCount());
			$this->panelElementSlotsUpdate();
			$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
			return;
		}
		$nbt = $out->getNamedTagEntry('IslandArchitect') ?? null;
		if ($nbt !== null) $nbt = $nbt->getTag('action') ?? null;
		if ($nbt !== null) switch ($nbt->getValue()) {

			case self::ITEM_REMOVE:
			case self::ITEM_LUCK:
			case self::ITEM_UNLUCK:
				$selected = $this->selected;
				$this->selected = null;

			case self::ITEM_REMOVE:
				$this->getRegex()->decreaseElementChance($selected->getId(), $selected->getDamage());

			case self::ITEM_LUCK:
				$this->getRegex()->increaseElementChance($selected->getId(), $selected->getDamage());

			case self::ITEM_UNLUCK:
				$this->getRegex()->decreaseElementChance($selected->getId(), $selected->getDamage(), 1);

			case self::ITEM_PREVIOUS:
				if ($this->display <= 0) break;
				$this->display -= self::PANEL_AVAILABLE_SLOTS_SIZE;

			case self::ITEM_NEXT:
				$totalitem = 0;
				if (!$this->collapse) foreach ($r->getAllElements() as $chance) $totalitem += $chance;
				else $totalitem = count($r->getAllElements());
				if (($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE >= (int)ceil($totalitem / self::PANEL_AVAILABLE_SLOTS_SIZE)) break;
				$this->display += self::PANEL_AVAILABLE_SLOTS_SIZE;

			case self::ITEM_SEED:
				$this->getPlayer()->removeWindow($inv);
				$transaction->then(function() use ($id, $m) : void {
					$this->editSeed();
				});

			case self::ITEM_ROLL:
				$this->panelRandomResultUpdate();

			case self::ITEM_COLLAPSE:
				$this->collapse = !$this->collapse;
				$this->display = 0;

			case self::ITEM_PREVIOUS:
			case self::ITEM_NEXT:
			case self::ITEM_COLLAPSE:
				$this->selected = null;

			case self::ITEM_COLLAPSE:
			case self::ITEM_PREVIOUS:
			case self::ITEM_NEXT:
			case self::ITEM_REMOVE:
			case self::ITEM_LUCK:
			case self::ITEM_UNLUCK:
				$this->panelSelect();

			default:
				$inv->sendContents($this->getSession()->getPlayer());
				break;

		} else {
			if ($out->getId() !== Item::AIR) {
				if (!isset($this->selected)) $this->selected = [$out->getId(), $out->getDamage()];
				else $this->selected = null;
				$this->panelSelect();
				$inv->sendContents($this->getSession()->getPlayer());
			}
		}
	}

	/**
	 * @var int
	 */
	private $random_rolled_times = 0;

	/**
	 * @var Item|null
	 */
	private $selected = null;

	/**
	 * @var int The positive offset of blocks(chances) display in the inventory, normally 33 as a page since there is 33 available slots.
	 * @see InvMenuSession::PANEL_AVAILABLE_SLOTS_SIZE
	 */
	private $display = 0;

	/**
	 * @var bool
	 */
	private $collapse = false;

	public const ITEM_REMOVE = 0;
	public const ITEM_LUCK = 1;
	public const ITEM_UNLUCK = 2;
	public const ITEM_PREVIOUS = 3;
	public const ITEM_NEXT = 4;
	public const ITEM_SEED = 5;
	public const ITEM_ROLL = 6;
	public const ITEM_COLLAPSE = 7;

	public function editRandom(?int $id = null, ?InvMenu $menu = null, bool $roll_next = true) : void {

		$i = Item::get(($this->display >= self::PANEL_AVAILABLE_SLOTS_SIZE ? Item::EMPTYMAP : Item::PAPER), 0, (int)ceil(($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_PREVIOUS)]));
		$inv->setItem(43 + 9, $i, false);

		$tdi = !$this->collapse ? $totalchance : count($r->getAllElements()); // Total display item
		$i = $i = Item::get($tdi / self::PANEL_AVAILABLE_SLOTS_SIZE < 1 ? Item::PAPER : Item::EMPTYMAP, max((int)ceil($tdi / self::PANEL_AVAILABLE_SLOTS_SIZE) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_NEXT)]));
		$inv->setItem(44 + 9, $i, false);

		$i = Item::get(Item::EXPERIENCE_BOTTLE, 0, $roll_next ? ++$this->random_rolled_times : $this->random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_ROLL)]));
		$inv->setItem(40 + 9, $i, false);
		
		if ($roll_next) {
			$i = $r->randomElementItem($this->random);
			if ($i->getBlock()->getId() === Item::AIR) {
				$i = Item::get(Item::END_PORTAL);
				$i->setCustomName(TF::GRAY . '(No random output)');
			} else $i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
			$inv->setItem(41 + 9, $i, false);
		}

		if (!isset($this->selected)) {
			$i = Item::get(Item::END_PORTAL);
			$i->setCustomName(TF::GRAY . '(No selected block)');
		} else {
			$i = clone $this->selected;
			$i->setCustomName(TF::RESET . TF::YELLOW . 'Selected block: ' . TF::BOLD . TF::GOLD . $i->getVanillaName());
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$inv->setItem(34 + 9, $i, false);

		$i = Item::get(Item::SHULKER_BOX, $this->collapse ? 14 : 5);
		$i->setCustomName(TF::RESET . TF::YELLOW . 'Show chance as block (Expand mode): ' . TF::BOLD . ($this->collapse ? TF::RED . 'Off' : TF::GREEN . 'On') . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to toggle)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_COLLAPSE)]));
		$inv->setItem(35 + 9, $i, false);

		$inv->sendContents($inv->getViewers());
	}

	public function editSeed(?int $id = null, ?InvMenu $menu = null) : void {
		$f = new CustomForm(function(Player $p, array $d = null) use ($id, $menu) : void {
			if ($d !== null and !empty($d[0] ?? null)) $this->random = new Random(empty(preg_replace('/[0-9-]+/i', '', $d[0])) ? (int)$d[0] : TemplateIslandGenerator::convertSeed($d[0]));
			$this->editRandom($id, $menu);
			$menu->send($this->getPlayer());
		});
		$f->addInput(TF::BOLD . TF::GOLD . 'Seed: ', 'Empty box to discard change', isset($this->random) ? (string)$this->random->getSeed() : '');
		$this->getPlayer()->sendForm($f);
	}

}