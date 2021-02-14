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
	 * @var int
	 */
	private $regex;

	public function __construct(PlayerSession $session, int $regex) {
		if (($regex = $session->getTemplateIsland()->getRandomById()) === null) throw new \InvalidArgumentException('Invalid regex ID');
		$this->session = $session;
		$this->regex = $regex;
	}
	/**
	 * @return RandomGeneration
	 */
	public function getRegex() : RandomGeneration {
		return $this->regex;
	}

	/**
	 * @var Random
	 */
	private $random = null;

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
		if (isset($this->randoms[$id])) $r = $this->randoms[$id];
		else $id = array_push($this->randoms, $r = new RandomGeneration) - 1;
		if (!class_exists(InvMenu::class)) {
			$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Cannot edit regex due to required virion "InvMenu" is not installed. ' . TF::RESET . TF::AQUA . 'A blank regex has been inserted into the island data, ' . TF::YELLOW . 'you can edit the regex after the island is exported!');
			self::giveRandomGenerationBlock($this->getPlayer(), $r);
			return;
		}
		if (!isset($menu)) {
			$m = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
			$m->send($this->getPlayer());
			$m->setListener(InvMenu::readonly(function (InvMenuTransaction $transaction) use ($r, $m, $id) : void {
				$in = $transaction->getIn();
				$out = $transaction->getOut();
				$inv = $m->getInventory();
				if (isset($transaction->getTransaction()->getInventories()[spl_object_hash($this->getPlayer()->getInventory())])) if ($in->getBlock()->getId() !== Item::AIR) {
					$r->increaseElementChance($in->getId(), $in->getDamage(), $in->getCount());
					$this->editRandom($id, $m);
					return;
				}
				$nbt = $out->getNamedTagEntry('IslandArchitect') ?? null;
				if ($nbt !== null) $nbt = $nbt->getTag('action') ?? null;
				if ($nbt !== null) switch ($nbt->getValue()) {
					case self::ITEM_REMOVE:
						if (!isset($this->selected)) break;
						$r->decreaseElementChance($this->selected->getId(), $this->selected->getDamage());
						$this->selected = null;
						$this->editRandom($id, $m);
						break;

					case self::ITEM_LUCK:
						if (!isset($this->selected)) break;
						$r->increaseElementChance($this->selected->getId(), $this->selected->getDamage());
						$this->editRandom($id, $m);
						break;

					case self::ITEM_UNLUCK:
						if (!isset($this->selected)) break;
						$r->decreaseElementChance($this->selected->getId(), $this->selected->getDamage(), 1);
						break;

					case self::ITEM_PREVIOUS:
						if ($this->display <= 0) break;
						$this->display -= 33;
						$this->editRandom($id, $m, false);
						break;

					case self::ITEM_NEXT:
						$totalitem = 0;
						if (!$this->collapse) foreach ($r->getAllRandomBlocks() as $chance) $totalitem += $chance;
						else $totalitem = count($r->getAllRandomBlocks());
						if (($this->display + 33) / 33 >= (int)ceil($totalitem / 33)) break;
						$this->display += 33;
						$this->editRandom($id, $m, false);
						break;

					case self::ITEM_SEED:
						$this->getPlayer()->removeWindow($inv);
						$transaction->then(function() use ($id, $m) : void {
							$this->editSeed($id, $m);
						});
						break;

					case self::ITEM_ROLL:
						$i = $r->randomElementItem($this->random);
						if ($i->getBlock()->getId() === Item::AIR) return;
						$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
						$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
						$inv->setItem(44, $i);
						break;

					case self::ITEM_COLLAPSE:
						$this->collapse = !$this->collapse;
						$this->editRandom($id, $m, false);
						break;

				} else {
					if ($out->getId() !== Item::AIR) {
						if (!isset($this->selected)) $this->selected = clone $out;
						else $this->selected = null;
						$this->editRandom($id, $m, false);
					}
				}
			}));
			$m->setInventoryCloseListener(function(Player $p, Inventory $inv) use ($id, $r) : void {
				self::giveRandomGenerationBlock($this->getPlayer(), $r);
			});
		} else $m = $menu;
		$inv = $m->getInventory();
		$m->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $id . (isset($this->selected) ? ' (Selected ' . $this->selected->getId() . ':' . $this->selected->getDamage() . ')' : ''));
		$totalchance = 0;
		for ($i=0; $i < $inv->getSize(); $i++) $inv->clear($i, false);
		foreach ($r->getAllRandomBlocks() as $chance) $totalchance += $chance;
		foreach ($r->getAllRandomBlocks() as $block => $chance) {
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
				if ($cti > ($this->display + 33)) continue;
				$inv->setItem($ti - $this->display - 1, $item, false);
			}
		}

		$i = Item::get(Item::INVISIBLEBEDROCK);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		foreach ([24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 42] as $slot) $inv->setItem($slot + 9, $i, false);

		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a block first)';
		$i = Item::get(Item::CONCRETE, 7);
		$i->setCustomName($prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_REMOVE)]));
		$inv->setItem(36 + 9, $i, false);

		/**
		 * @todo Disable this action item if the chance of a block is over or equals 32767
		 */
		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_LUCK)]));
		$inv->setItem(37 + 9, $i, false);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Decrease chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_UNLUCK)]));
		$inv->setItem(38 + 9, $i, false);

		$i = Item::get(($this->display >= 33 ? Item::EMPTYMAP : Item::PAPER), 0, (int)ceil(($this->display + 33) / 33));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_PREVIOUS)]));
		$inv->setItem(43 + 9, $i, false);

		$tdi = !$this->collapse ? $totalchance : count($r->getAllRandomBlocks()); // Total display item
		$i = $i = Item::get($tdi / 33 < 1 ? Item::PAPER : Item::EMPTYMAP, max((int)ceil($tdi / 33) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::ITEM_NEXT)]));
		$inv->setItem(44 + 9, $i, false);

		if (!isset($this->random)) $this->random = new Random(random_int(INT32_MIN, INT32_MAX));
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
		$inv->setItem(39 + 9, $i, false);

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

	public static function giveRandomGenerationBlock(Player $player, RandomGeneration $randomgeneration, bool $removeDuplicatedItem = true) : void {
		$inv = $player->getInventory();
		if ($removeDuplicatedItem) foreach ($inv->getContents() as $index => $i) if (($nbt = $i->getNamedTagEntry('IslandArchitect')) !== null) if (($nbt = $nbt->getCompoundTag('random-generation')) !== null) if (($nbt = $nbt->getListTag('regex')) !== null) if (RandomGeneration::fromNBT($nbt)->equals($randomgeneration)) $inv->clear($index);
		foreach ($randomgeneration->getAllRandomBlocks() as $block => $chance) {
			$block = explode(':', $block);
			$regex[] = new CompoundTag('', [
				new ShortTag('id', (int)$block[0]),
				new ByteTag('meta', (int)($block[1] ?? 0)),
				new ShortTag('chance', (int)$chance)
			]);
		}
		$i = Item::get(Item::CYAN_GLAZED_TERRACOTTA, 0, 64);
		foreach ($randomgeneration->getAllRandomBlocks() as $block => $chance) {
			$block = explode(':', $block);
			$bi = Item::get((int)$block[0], (int)($block[1] ?? 0));
			$blockslore[] = $bi->getName() . ' (' . $bi->getId() . ':' . $bi->getDamage() . '): ' . TF::BOLD . TF::GREEN . $chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)';
		}
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . 'Random generation' . (!empty($blockslore ?? []) ? ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $blockslore ?? []) : ''));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new CompoundTag('random-generation', [
			new ListTag('regex', $regex ?? [])
		])]));
		$inv->addItem($i);
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