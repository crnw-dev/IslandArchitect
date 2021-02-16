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
use function class_exists;
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

	/**
	 * @var Closure|null
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
	 * @var Item|null
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

	public const PANEL_AVAILABLE_SLOTS_SIZE = 33;

	public const ITEM_REMOVE = 0;
	public const ITEM_LUCK = 1;
	public const ITEM_UNLUCK = 2;
	public const ITEM_PREVIOUS = 3;
	public const ITEM_NEXT = 4;
	public const ITEM_SEED = 5;
	public const ITEM_ROLL = 6;
	public const ITEM_COLLAPSE = 7;

	protected function panelInit() : void {
		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->menu->setListener(InvMenu::readonly(\Closure::fromCallable([$this, 'transactionCallback'])));
		$this->menu->setInventoryCloseListener(function(Player $p, Inventory $inv) : void {
			if ($this->giveitem_lock) return;
			$i = $this->getRegex()->getRandomGenerationItem();
			$i->getNamedTagEntry('IslandArchitect')->getCompoundTag('random-generation')->setShort('regexid', $this->getRegexId());
			$p->getInventory()->addItem();
			if (isset($this->callback)) ($this->callback)();
		});

		$i = Item::get(Item::INVISIBLEBEDROCK);
		$i->setCustomName('');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		foreach ([33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 51] as $slot) $this->menu->getInventory()->setItem($slot, $i, false);

		$this->random = new Random(random_int(INT32_MIN, INT32_MAX));
		if (class_exists(CustomForm::class)) $this->panelSeed();
		else {
			$i = Item::get(Item::PUMPKIN_SEEDS);
			$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)$this->random->getSeed() . "\n\n" . TF::RESET . TF::BOLD . TF::RED . 'Cannot edit seed due to ' . "\n" . 'required virion "FormAPI" is not installed.');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
			$this->menu->getInventory()->setItem(48, $i, false);
		}
		$this->panelElementSlots();
		$this->panelSelect();
		$this->panelPage();
		$this->panelCollapse();
		$this->panelRandom();
	}

	protected function panelElementSlots() : void {
		if ($this->menu->getName() !== TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $this->getRegexId()) $this->menu->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $this->getRegexId());
		for ($i=0; $i < self::PANEL_AVAILABLE_SLOTS_SIZE; $i++) $this->menu->getInventory()->clear($i, false);
		$totalchance = $this->getRegex()->getTotalChance();
		foreach ($this->getRegex()->getAllElements() as $block => $chance) {
			$block = explode(':', $block);
			$selected = false;
			if (isset($this->selected)) $selected = (int)$block[0] === (int)$this->selected[0] and (int)$block[1] === (int)$this->selected[1];
			if (!$selected) $item = Item::get((int)$block[0], (int)($block[1]));
			else $item = Item::get(Item::WOOL, 5);
			$item->setCustomName(
				TF::RESET . $item->getVanillaName() . "\n" .
				TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$block[0] . "\n" .
				TF::RESET . TF::YELLOW . 'Meta: ' . TF::BOLD . TF::GOLD . (int)$block[1] . "\n" .
				TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance == 0 ? (int)$chance : $totalchance) * 100, 2) . '%%)' . "\n\n" .
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
				$this->menu->getInventory()->setItem($ti - $this->display - 1, $item, false);
			}
		}
	}

	protected function panelSelect() : void {
		$s = $this->selected !== null;
		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a block first)';

		$i = Item::get(Item::CONCRETE, $s ? 14 : 7);
		$i->setCustomName($s ? TF::RESET . TF::BOLD . TF::RED . 'Remove' : $prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $s ? self::ITEM_REMOVE : -1)]));
		$this->menu->getInventory()->setItem(45, $i, false);

		$limit = "\n" . TF::ITALIC . TF::RED . '(Limit reached)';
		$e = ($s and ($this->getRegex()->getElementChance($this->selected[0], $this->selected[1]) < 32767));
		$i = Item::get($e ? Item::EMERALD_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::GREEN . 'Increase chance' : (
			!$s ? $prefix . 'Increase chance' . $surfix : $prefix . 'Increase chance' . $limit
		));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_LUCK : -1)]));
		$this->menu->getInventory()->setItem(46, $i, false);

		$e = ($s and ($this->getRegex()->getElementChance($this->selected[0], $this->selected[1]) > 1));
		$i = Item::get($e ? Item::REDSTONE_ORE : Item::STONE);
		$i->setCustomName($e ? TF::RESET . TF::BOLD . TF::RED . 'Decrease chance' : (
			!$s ? $prefix . 'Decrease chance' . $surfix : $prefix . 'Decrease chance' . $limit
		));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', $e ? self::ITEM_UNLUCK : -1)]));
		$this->menu->getInventory()->setItem(47, $i, false);

		if (!isset($this->selected)) {
			$i = Item::get(-161);
			$i->setCustomName(TF::GRAY . '(No selected block)');
		} else {
			$chance = $this->getRegex()->getElementChance($this->selected[0], $this->selected[1]);
			$totalchance = $this->getRegex()->getTotalChance();
			$i = Item::get($this->selected[0], $this->selected[1]);
			$i->setCustomName(
				TF::RESET . $i->getVanillaName() . "\n" .
				TF::YELLOW . 'ID: ' . TF::BOLD . TF::GOLD . (int)$this->selected[0] . "\n" .
				TF::RESET . TF::YELLOW . 'Meta: ' . TF::BOLD . TF::GOLD . (int)$this->selected[1] . "\n" .
				TF::RESET . TF::YELLOW . TF::YELLOW . 'Chance: ' . TF::BOLD . TF::GREEN . (int)$chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance == 0 ? (int)$chance : $totalchance) * 100, 2) . '%%)' . "\n\n" .
				TF::RESET . TF::ITALIC . TF::GRAY . '(Selected item)'
			);
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$this->menu->getInventory()->setItem(43, $i, false);
	}

	protected function panelSeed() : void {
		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)$this->random->getSeed() . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to edit seed or reset random)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_SEED)]));
		$this->menu->getInventory()->setItem(48, $i, false);
	}

	protected function transactionCallback(InvMenuTransaction $transaction) : void {
		$in = $transaction->getIn();
		$out = $transaction->getOut();
		if (self::itemConversion($in)->getBlock()->getId() !== Item::AIR) $this->menu->setName(TF::DARK_BLUE . TF::BOLD . '#' . $this->getRegexId() . ': ' . TF::RED . 'Invalid item!');
		elseif (isset($transaction->getTransaction()->getInventories()[spl_object_hash($this->getSession()->getPlayer()->getInventory())])) {
			$this->getRegex()->increaseElementChance($in->getId(), $in->getDamage(), $in->getCount());
			$this->panelSelect();
			$this->panelElementSlots();
			$this->panelRandom();
			$this->panelPage();
			$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
			return;
		}
		$nbt = $out->getNamedTagEntry('IslandArchitect') ?? null;
		if ($nbt !== null) $nbt = $nbt->getTag('action', ByteTag::class) ?? null;
		if ($nbt !== null) switch ($nbt->getValue()) {

			case self::ITEM_REMOVE:
				$selected = $this->selected;
				$this->selected = null;
				$this->getRegex()->decreaseElementChance($selected[0], $selected[1]);
				$this->panelSelect();
				$this->panelElementSlots();
				$this->panelRandom();
				$this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_LUCK:
				$selected = $this->selected;
				$this->getRegex()->increaseElementChance($selected[0], $selected[1]);
				$this->panelSelect();
				$this->panelElementSlots();
				$this->panelRandom();
				$this->panelPage();
				if (!$this->collapse) $this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_UNLUCK:
				$selected = $this->selected;
				$this->getRegex()->decreaseElementChance($selected[0], $selected[1], 1);
				$this->panelSelect();
				$this->panelElementSlots();
				$this->panelRandom();
				$this->panelPage();
				if (!$this->collapse) $this->panelPage();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_PREVIOUS:
				if ($this->display <= 0) break;
				$this->display -= self::PANEL_AVAILABLE_SLOTS_SIZE;
				$this->panelPage();
				$this->panelElementSlots();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_NEXT:
				$totalitem = 0;
				if (!$this->collapse) foreach ($this->getRegex()->getAllElements() as $chance) $totalitem += $chance;
				else $totalitem = count($this->getRegex()->getAllElements());
				if (($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE >= (int)ceil($totalitem / self::PANEL_AVAILABLE_SLOTS_SIZE)) break;
				$this->display += self::PANEL_AVAILABLE_SLOTS_SIZE;
				$this->panelPage();
				$this->panelElementSlots();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

			case self::ITEM_SEED:
				$this->giveitem_lock = true;
				$this->getSession()->getPlayer()->removeWindow($this->menu->getInventory());
				$this->giveitem_lock = false;
				$transaction->then(function() : void {
					$this->editSeed();
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
				$this->panelElementSlots();
				$this->panelCollapse();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
				break;

		} else {
			if ($out->getId() !== Item::AIR) {
				if (!isset($this->selected)) $this->selected = [$out->getId(), $out->getDamage()];
				else $this->selected = null;
				$this->panelSelect();
				$this->panelElementSlots();
				$this->menu->getInventory()->sendContents($this->getSession()->getPlayer());
			}
		}
	}

	protected function panelPage() : void {
		$i = Item::get(($this->display >= self::PANEL_AVAILABLE_SLOTS_SIZE ? Item::EMPTYMAP : Item::PAPER), 0, max((int)ceil(($this->display + self::PANEL_AVAILABLE_SLOTS_SIZE) / self::PANEL_AVAILABLE_SLOTS_SIZE) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_PREVIOUS)]));
		$this->menu->getInventory()->setItem(52, $i, false);

		$tdi = !$this->collapse ? $this->getRegex()->getTotalChance() : count($this->getRegex()->getAllElements()); // Total display item
		$i = Item::get(($tdi - $this->display) / self::PANEL_AVAILABLE_SLOTS_SIZE < 1 ? Item::PAPER : Item::EMPTYMAP, 0, max((int)ceil(($tdi - $this->display) / self::PANEL_AVAILABLE_SLOTS_SIZE) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_NEXT)]));
		$this->menu->getInventory()->setItem(53, $i, false);
	}

	protected function panelRandom() : void {
		$i = Item::get(Item::EXPERIENCE_BOTTLE, 0,++$this->random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_ROLL)]));
		$this->menu->getInventory()->setItem(49, $i, false);
		
		if (empty($this->getRegex()->getAllElements())) {
			$i = Item::get(-161);
			$i->setCustomName(TF::GRAY . '(No random output)');
		} else {
			$i = $this->getRegex()->randomElementItem($this->random);
			$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$this->menu->getInventory()->setItem(50, $i, false);
	}

	protected function panelCollapse() : void {
		$i = Item::get(Item::SHULKER_BOX, $this->collapse ? 14 : 5);
		$i->setCustomName(TF::RESET . TF::YELLOW . 'Show chance as block (Expand mode): ' . TF::BOLD . ($this->collapse ? TF::RED . 'Off' : TF::GREEN . 'On') . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to toggle)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_COLLAPSE)]));
		$this->menu->getInventory()->setItem(44, $i, false);
	}

	public function editSeed() : void {
		$f = new CustomForm(function(Player $p, array $d = null) : void {
			if ($d !== null and !empty($d[0] ?? null)) {
					$this->random = new Random(empty(preg_replace('/[0-9-]+/i', '', $d[0])) ? (int)$d[0] : TemplateIslandGenerator::convertSeed($d[0]));
					$this->random_rolled_times = 0;
					$this->panelSeed();
					$this->panelRandom();
				}
			$this->menu->send($this->getSession()->getPlayer());
		});
		$f->addInput(TF::BOLD . TF::GOLD . 'Seed: ', 'Empty box to discard change', isset($this->random) ? (string)$this->random->getSeed() : '');
		$this->getSession()->getPlayer()->sendForm($f);
	}

}