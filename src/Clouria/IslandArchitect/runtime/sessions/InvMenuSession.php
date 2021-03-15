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
namespace Clouria\IslandArchitect\runtime\sessions;

use pocketmine\{
	Player,
	item\Item,
	utils\TextFormat as TF,
	utils\Random,
	inventory\Inventory,
    level\generator\Generator
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag,
	ByteTag
};

use muqsit\invmenu\{
	InvMenu,
	transaction\DeterministicInvMenuTransaction as InvMenuTransaction
};
use jojoe77777\FormAPI\CustomForm;

use Clouria\IslandArchitect\{
	IslandArchitect,
	runtime\RandomGeneration
};

use function max;
use function explode;
use function random_int;
use function ceil;
use function count;
use function preg_replace;
use function class_exists;
use function spl_object_hash;
use function min;

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

	/**
	 * @var \Closure|null
	 */
	private $callback;

	public function __construct(PlayerSession $session, ?int $regexid = null, ?\Closure $callback = null) {
		if ($regexid === null) {
			$regex = new RandomGeneration;
			$regexid = $session->getIsland()->addRandom($regex);
		} elseif (($regex = $session->getIsland()->getRandomById($regexid)) === null) {
			$regex = new RandomGeneration;
			$regexid = $session->getIsland()->addRandom($regex);
		}
		$this->session = $session;
		$this->regexid = $regexid;
		$this->regex = $regex;
		$this->callback = $callback;
		if (!class_exists(InvMenu::class)) {
			$session->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Cannot open random generation regex modify panel, ' . "\n" . 'required virion "InvMenu(v4)" is not installed. ' . TF::AQUA . 'A blank regex has been added into your island data, you may edit the regex manually with an text editor!');
			return;
		}

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

	/**
	 * @var int
	 */
	protected $random_rolled_times = 0;

	/**
	 * @var array<int, mixed>
	 */
	protected $selected = null;

	/**
	 * @var int The positive offset of elements(multiplied by the chance if expanded) display in the inventory, normally 33 as a page since there is 33 available slots.
	 * @see InvMenuSession::PANEL_AVAILABLE_SLOTS_SIZE
	 */
	protected $display = 0;

	/**
	 * @var bool
	 */
	protected $collapse = false;

	/**
	 * @var bool
	 */
	protected $giveitem_lock = false;

	public const PANEL_AVAILABLE_SLOTS_SIZE = 32;

	public const ITEM_REMOVE = 0;
	public const ITEM_LUCK = 1;
	public const ITEM_UNLUCK = 2;
	public const ITEM_PREVIOUS = 3;
	public const ITEM_NEXT = 4;
	public const ITEM_SEED = 5;
	public const ITEM_ROLL = 6;
	public const ITEM_COLLAPSE = 7;
	public const ITEM_SYMBOLIC = 8;
	public const ITEM_LABEL = 9;

	protected function panelInit() : void {
		if (!isset($this->menu)) {
			$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
			$this->menu->setInventoryCloseListener(function(Player $p, Inventory $inv) : void {
				if ($this->giveitem_lock) return;
				$i = $this->getSession()->getIsland()->getRandomSymbolicItem($this->getRegexId());
				$i->setCount(64);
				$i = $this->getRegex()->getRandomGenerationItem($i, $this->getRegexId());
				$p->getInventory()->addItem($i);
				if (isset($this->callback)) ($this->callback)();
			});
			$this->menu->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $this->getRegexId());
		}
		$this->menu->setListener(InvMenu::readonly(\Closure::fromCallable([$this, 'transactionCallback'])));

		$i = Item::get(Item::INVISIBLEBEDROCK);
		$i->setCustomName(' ');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		foreach ([32, 33, 34, 35, 36, 37, 38, 39, 40, 41] as $slot) $this->menu->getInventory()->setItem($slot, $i, false);

		$this->random = new Random(self::getDefaultSeed() ?? random_int(INT32_MIN, INT32_MAX));
		if (class_exists(CustomForm::class)) {
		    $this->panelSeed();
		    $this->panelLabel();
        }
		else {
			$i = Item::get(Item::PUMPKIN_SEEDS);
			$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)$this->random->getSeed() . "\n\n" . TF::RESET . TF::BOLD . TF::RED . 'Cannot edit seed, ' . "\n" . 'required virion "FormAPI" is not installed.');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
			$this->menu->getInventory()->setItem(42, $i, false);

			$i = Item::get(Item::DARKOAK_SIGN);
			$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)$this->random->getSeed() . "\n\n" . TF::RESET . TF::BOLD . TF::RED . 'Cannot edit regex label, ' . "\n" . 'required virion "FormAPI" is not installed.');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
			$this->menu->getInventory()->setItem(50, $i, false);
		}

		$this->panelElement();
		$this->panelSelect();
		$this->panelPage();
		$this->panelCollapse();
		$this->panelRandom();
		$this->panelSymbolic();
	}

	protected function panelElement() : void {
		for ($i=0; $i < self::PANEL_AVAILABLE_SLOTS_SIZE; $i++) $this->menu->getInventory()->clear($i, false);
		$totalchance = $this->getRegex()->getTotalChance();
		foreach ($this->getRegex()->getAllElements() as $block => $chance) {
			$block = explode(':', $block);
			$selected = false;
			if (isset($this->selected)) $selected = ((int)$block[0] === (int)$this->selected[0] and (int)$block[1] === (int)$this->selected[1]);
			$item = Item::get((int)$block[0], (int)($block[1]));
			$itemname = $item->getVanillaName();
			if ($selected) $item = Item::get(Item::WOOL, 5);
			elseif ($item->getId() === Item::AIR) $item = self::displayConversion($item);
			$item->setCustomName(
				TF::RESET . $itemname . "\n" .
				TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$block[0] . "\n" .
				TF::RESET . TF::YELLOW . 'Meta: ' . TF::BOLD . TF::GOLD . (int)$block[1] . "\n" .
				TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . ' / ' . ($totalchanceNonZero = $totalchance == 0 ? (int)$chance : $totalchance) . TF::ITALIC . ' (' . round((int)$chance / $totalchanceNonZero * 100, 2) . '%%)' . "\n\n" .
				TF::RESET . TF::ITALIC . TF::GRAY . (!$selected ? '(Click / drop to select this element)' : '(Click / drop again to cancel the select)'));
			$item->setNamedTagEntry(new CompoundTag('IslandArchitect', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)$block[1])
			]));
			for ($i=0; $i < (!$this->collapse ? max((int)$chance, 1) : 1); $i++) {
				if (!isset($ti)) $ti = 0;
				$cti = ++$ti;
				if ($cti <= $this->display) continue;
				if ($cti > ($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE)) continue;
				$this->menu->getInventory()->setItem($ti - $this->display - 1, $item, false);
			}
		}
	}

	protected function panelSelect() : void {
		$s = $this->selected !== null;
		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$suffix = "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a element first)';

		$i = Item::get(Item::CONCRETE, $s ? 14 : 7);
		$i->setCustomName($s ? TF::RESET . TF::BOLD . TF::RED . 'Remove' : $prefix . 'Remove' . $suffix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $s ? self::ITEM_REMOVE : -1)]));
		$this->menu->getInventory()->setItem(46, $i, false);

		$limit = "\n" . TF::ITALIC . TF::RED . '(Limit reached)';
		$e = ($s and ($this->getRegex()->getElementChance($this->selected[0], $this->selected[1]) < 32767));
		$i = Item::get($e ? Item::EMERALD_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::GREEN . 'Increase chance' : (
			!$s ? $prefix . 'Increase chance' . $suffix : $prefix . 'Increase chance' . $limit
		));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_LUCK : -1)]));
		$this->menu->getInventory()->setItem(49, $i, false);

		$e = ($s and ($this->getRegex()->getElementChance($this->selected[0], $this->selected[1]) > 1));
		$i = Item::get($e ? Item::REDSTONE_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::RED . 'Decrease chance' : (
			!$s ? $prefix . 'Decrease chance' . $suffix : $prefix . 'Decrease chance' . $limit
		));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_UNLUCK : -1)]));
		$this->menu->getInventory()->setItem(48, $i, false);

		if (!isset($this->selected)) {
			$i = self::getBarrier();
			$i->setCustomName(TF::GRAY . '(No selected element)');
		} else {
			$chance = $this->getRegex()->getElementChance($this->selected[0], $this->selected[1]);
			$totalchance = $this->getRegex()->getTotalChance();
			$i = Item::get($this->selected[0], $this->selected[1]);
			$itemname = $i->getVanillaName();
			$i = self::displayConversion($i);
			$i->setCustomName(
				TF::RESET . $itemname . "\n" .
				TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$this->selected[0] . "\n" .
				TF::RESET . TF::YELLOW . 'Meta: ' . TF::BOLD . TF::GOLD . (int)$this->selected[1] . "\n" .
				TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . ' / ' . ($totalchanceNonZero = $totalchance == 0 ? (int)$chance : $totalchance) . TF::ITALIC . ' (' . round((int)$chance / $totalchanceNonZero * 100, 2) . '%%)' . "\n\n" .
				TF::RESET . TF::ITALIC . TF::GRAY . '(Selected element)'
			);
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$this->menu->getInventory()->setItem(47, $i, false);
	}

	protected function panelSeed() : void {
		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . $this->random->getSeed() . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to edit seed or reset random noises)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_SEED)]));
		$this->menu->getInventory()->setItem(42, $i, false);
	}

	protected function transactionCallback(InvMenuTransaction $transaction) : void {
		$inraw = $transaction->getIn();
		$in = $inraw;
		$in = self::inputConversion($in, $successed);
		$out = $transaction->getOut();
		if (
			(
				$successed or
				$in->getBlock()->getId() !== Item::AIR
			) and
			($transaction->getTransaction()->getInventories()[spl_object_hash($this->getSession()->getPlayer()->getInventory())] ?? null) !== null
		) {
			$this->getRegex()->increaseElementChance($in->getId(), $in->getDamage(), $in->getCount());
			$this->panelSelect();
			$this->panelElement();
			$this->panelRandom();
			$this->panelPage();
			$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
			return;
		}
		$nbt = $out->getNamedTagEntry('IslandArchitect') ?? null;
		if ($nbt instanceof CompoundTag) $nbt = $nbt->getTag('action', ByteTag::class) ?? null;
		if ($nbt !== null) switch ($nbt->getValue()) {

			case self::ITEM_REMOVE:
				$selected = $this->selected;
				$this->selected = null;
				$this->getRegex()->decreaseElementChance($selected[0], $selected[1]);
				$this->panelSelect();
				$this->panelElement();
				$this->panelRandom();
				$this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_LUCK:
				$selected = $this->selected;
				$this->getRegex()->increaseElementChance($selected[0], $selected[1]);
				$this->panelSelect();
				$this->panelElement();
				$this->panelRandom();
				$this->panelPage();
				if (!$this->collapse) $this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_UNLUCK:
				$selected = $this->selected;
				$this->getRegex()->decreaseElementChance($selected[0], $selected[1], 1);
				$this->panelSelect();
				$this->panelElement();
				$this->panelRandom();
				$this->panelPage();
				if (!$this->collapse) $this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_PREVIOUS:
				if ($this->display <= 0) break;
				$this->display -= self::PANEL_AVAILABLE_SLOTS_SIZE;
				$this->panelPage();
				$this->panelElement();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_NEXT:
				$totalitem = 0;
				if (!$this->collapse) foreach ($this->getRegex()->getAllElements() as $chance) $totalitem += $chance;
				else $totalitem = count($this->getRegex()->getAllElements());
				if (($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE >= (int)ceil($totalitem / self::PANEL_AVAILABLE_SLOTS_SIZE)) break;
				$this->display += self::PANEL_AVAILABLE_SLOTS_SIZE;
				$this->panelPage();
				$this->panelElement();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_SEED:
			case self::ITEM_LABEL:
				$this->giveitem_lock = true;
				$this->getSession()->getPlayer()->removeWindow($this->menu->getInventory());
				$this->giveitem_lock = false;
				$transaction->then(function() use ($nbt) : void {
					if ($nbt->getValue() == self::ITEM_SEED) $this->editSeed();
					else $this->editLabel();
				});
				break;

			case self::ITEM_ROLL:
				$this->panelRandom();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_COLLAPSE:
				$this->collapse = !$this->collapse;
				if ($this->getRegex()->getTotalChance() > self::PANEL_AVAILABLE_SLOTS_SIZE) {
					$this->display = 0;
					$this->panelPage();
				}
				$this->panelElement();
				$this->panelCollapse();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_SYMBOLIC:
				$this->editSymbolic();
				break;

		} else {
			if ($out->getId() !== Item::AIR) {
				if (!isset($this->selected)) $this->selected = [$out->getNamedTagEntry('IslandArchitect')->getShort('id'), $out->getNamedTagEntry('IslandArchitect')->getByte('meta')];
				else $this->selected = null;
				$this->panelSelect();
				$this->panelElement();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
			}
		}
	}

	protected function panelPage() : void {
		$i = Item::get((($enabled = $this->display >= self::PANEL_AVAILABLE_SLOTS_SIZE) ? Item::EMPTYMAP : Item::PAPER), 0, min($pages = max((int)ceil(($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE) - 1, 1), 100));
		$i->setCustomName(TF::RESET . TF::BOLD . ($enabled ? TF::YELLOW . 'Previous page' . TF::ITALIC . TF::GOLD . ' (' . $pages . ')' : TF::GRAY . 'Previous page'));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_PREVIOUS)]));
		$this->menu->getInventory()->setItem(52, $i, false);

		$tdi = !$this->collapse ? $this->getRegex()->getTotalChance() : count($this->getRegex()->getAllElements()); // Total display item
		$i = Item::get(($enabled = (($tdi - $this->display) / self::PANEL_AVAILABLE_SLOTS_SIZE > 1)) ? Item::EMPTYMAP : Item::PAPER, 0, min($pages = max((int)ceil(($tdi - $this->display) / self::PANEL_AVAILABLE_SLOTS_SIZE) - 1, 1), 100));
		$i->setCustomName(TF::RESET . TF::BOLD . ($enabled ? TF::YELLOW . 'Next page' . TF::ITALIC . TF::GOLD . ' (' . $pages .')' : TF::GRAY . 'Next page'));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_NEXT)]));
		$this->menu->getInventory()->setItem(53, $i, false);
	}

	protected function panelRandom() : void {
		$i = Item::get(Item::EXPERIENCE_BOTTLE, 0, min(max($this->random_rolled_times++, 1), 100));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll' . "\n" . TF::RESET . TF::YELLOW . 'Rolled times: ' . TF::BOLD . TF::GOLD . ($this->random_rolled_times - 1));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_ROLL)]));
		$this->menu->getInventory()->setItem(44, $i, false);
		
		if (empty($this->getRegex()->getAllElements())) {
			$i = self::getBarrier();
			$i->setCustomName(TF::GRAY . '(No random output)');
		} else {
			$i = $this->getRegex()->randomElementItem($this->random);
			$itemname = $i->getVanillaName();
			$i = self::displayConversion($i);	
			$i->setCustomName(TF::RESET . $itemname . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$this->menu->getInventory()->setItem(43, $i, false);
	}

	protected function panelCollapse() : void {
		$i = Item::get(Item::SHULKER_BOX, $this->collapse ? 14 : 5);
		$i->setCustomName(TF::RESET . TF::YELLOW . 'Expand mode: ' . TF::BOLD . ($this->collapse ? TF::RED . 'Off' : TF::GREEN . 'On') . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to toggle)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_COLLAPSE)]));
		$this->menu->getInventory()->setItem(51, $i, false);
	}

	protected function panelSymbolic() : void {
		$i = $this->getSession()->getIsland()->getRandomSymbolicItem($this->getRegexId());
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Change regex symbolic');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_SYMBOLIC)]));
		$this->menu->getInventory()->setItem(45, $i, false);
	}

	protected function panelLabel() : void {
        $i = Item::get(Item::BIRCH_SIGN);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . $this->getSession()->getIsland()->getRandomLabel($this->getRegexId()) . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to rename regex label)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_LABEL)]));
		$this->menu->getInventory()->setItem(50, $i, false);
    }

	public function editSeed() : void {
		$f = new CustomForm(function(Player $p, array $d = null) : void {
			if ($d !== null and !empty($d[0] ?? null)) {
					$this->random = new Random(empty(preg_replace('/[0-9-]+/i', '', $d[0])) ? (int)$d[0] : Generator::convertSeed($d[0]));
					$this->random_rolled_times = 0;
					$this->panelSeed();
					$this->panelRandom();
				}
			$this->menu->send($this->getSession()->getPlayer());
		});
		$f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Edit Seed');
		$f->addInput(TF::BOLD . TF::ITALIC . TF::GRAY . '(Empty box to discard change)', (string)$this->random->getSeed(), isset($this->random) ? (string)$this->random->getSeed() : '');
		$this->getSession()->getPlayer()->sendForm($f);
	}

	public function editLabel() : void {
        $f = new CustomForm(function(Player $p, array $d = null) : void {
			if ($d !== null) {
                if (!empty($d[0] ?? null)) $this->getSession()->getIsland()->setRandomLabel($this->getRegexId(), (string)$d[0]);
                else $this->getSession()->getIsland()->resetRandomLabel($this->getRegexId());
                $this->panelLabel();
            }
			$this->menu->send($this->getSession()->getPlayer());
		});
        $f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Edit Label');
		$f->addInput(TF::BOLD . TF::ITALIC . TF::GRAY . '(Empty box to reset)', TF::BOLD . 'Regex #' . $this->getRegexId());
		$this->getSession()->getPlayer()->sendForm($f);
    }

	protected function editSymbolic() : void {
		for ($i=0; $i < $this->menu->getInventory()->getSize(); $i++) {
			switch ($i) {
				case 45:
					$item = Item::get(Item::AIR);
					break;

				// Arrow head
				case 19:
				case 28:
				case 37:
				case 38:
				case 39:

				// Arrow body
				case 29:
				case 21:
				case 13:
				case 5:
					$item = Item::get(Item::IRON_BARS);
					break;

				default:
					$item = Item::get(Item::INVISIBLEBEDROCK);
					break;
			}
			$item->setCustomName(TF::GRAY . '(Move your symbolic block into the empty slot' . "\n" . 'or click / drop item in other slots to cancel)');
			$this->menu->getInventory()->setItem($i, $item, false);
		}
		$this->menu->setListener(InvMenu::readonly(function(InvMenuTransaction $transaction) : void {
			$in = $transaction->getIn();
			$out = $transaction->getOut();
			if ($out->getId() === Item::AIR) {
				if ($in->getBlock()->getId() === Item::AIR or ($transaction->getTransaction()->getInventories()[spl_object_hash($this->getSession()->getPlayer()->getInventory())] ?? null) === null) return;
				$in = self::inputConversion($in);
				$this->getSession()->getIsland()->setRandomSymbolic($this->getRegexId(), $in->getId(), $in->getDamage());
			}
			$this->panelInit();
			$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
		}));
		$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
	}

    /**
     * @param Item $item
     * @param bool $successed
     * @return Item|\pocketmine\item\ItemBlock
     */
	protected static function inputConversion(Item $item, &$successed = false) : Item {
		$successed = false;
		$count = $item->getCount();
		$nbt = $item->getNamedTag();
		switch (true) {
			case $item->getId() === Item::BUCKET and $item->getDamage() === 8:
			case $item->getId() === Item::POTION and $item->getDamage() === 0:
				$item = Item::get(Item::WATER);
				$successed = true;
				break;

			case $item->getId() === Item::BUCKET and $item->getDamage() === 10:
				$item = Item::get(Item::LAVA);
				$successed = true;
				break;

			case $item->getId() === Item::BUCKET and $item->getDamage() === 0:
			case $item->getId() === Item::GLASS_BOTTLE and $item->getDamage() === 0:
			case $item->getId() === Item::BOWL and $item->getDamage() === 0:
				$item = Item::get(Item::AIR);
				$successed = true;
				break;
		}
		$item->setCount($count);
		foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
		return $item;
	}

	protected static function displayConversion(Item $item, &$successed = false) : Item {
		$successed = false;
		$count = $item->getCount();
		$nbt = $item->getNamedTag();
		switch (true) {
			case $item->getId() === Item::AIR:
				$item = Item::get(self::allowUnstableItem() ? 217 : Item::INFO_UPDATE);
				$successed = true;
				break;
		}
		$item->setCount($count);
		foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
		return $item;
	}

	protected static function getBarrier() : Item {
		return self::allowUnstableItem() ? Item::get(-161) : Item::get(Item::WOOL, 14);
	}

	public static function allowUnstableItem() : bool {
		return (bool)IslandArchitect::getInstance()->getConfig()->get('panel-allow-unstable-item', true);
	}

	public static function getDefaultSeed() : ?int {
		$seed = IslandArchitect::getInstance()->getConfig()->get('panel-default-seed', null);
		if ($seed !== null) $seed = (int)$seed;
		return $seed;
	}

}