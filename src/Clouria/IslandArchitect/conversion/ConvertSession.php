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
	item\Item,
	block\Block,
	math\Vector3,
	utils\TextFormat as TF,
	utils\Random,
	scheduler\TaskHandler,
	scheduler\ClosureTask,
	inventory\Inventory
};
use pocketmine\event\{
	player\PlayerChatEvent,
	player\PlayerInteractEvent,
	block\BlockPlaceEvent
};
use pocketmine\nbt\tag\{
	CompoundTag,
	ShortTag,
	ByteTag,
	ListTag,
	StringTag
};

use muqsit\invmenu\{
	InvMenu,
	transaction\DeterministicInvMenuTransaction as InvMenuTransaction
};

use Clouria\IslandArchitect\{
	IslandArchitect,
	api\RandomGeneration,
	api\RandomGenerationTile,
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
	public function getRandoms() : array {
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
	 * @var int The positive offset of blocks(chances) display in the inventory, normally 33 as a page since there is 33 available slots.
	 */
	private $invmenu_display = 0;

	/**
	 * @var bool
	 */
	private $invmenu_collapse = false;

	/**
	 * @var bool A random generation item won't be give to player if this is true
	 */
	private $invmenu_item_lock = false;

	public const INVMENU_ITEM_REMOVE = 0;
	public const INVMENU_ITEM_LUCK = 1;
	public const INVMENU_ITEM_UNLUCK = 2;
	public const INVMENU_ITEM_PREVIOUS = 3;
	public const INVMENU_ITEM_NEXT = 4;
	public const INVMENU_ITEM_SEED = 5;
	public const INVMENU_ITEM_ROLL = 6;
	public const INVMENU_ITEM_COLLAPSE = 7;

	public function editRandom(?int $id = null, ?InvMenu $menu = null, bool $roll_next = true) : void {
		if (isset($this->randoms[$id])) $r = $this->randoms[$id];
		else $id = array_push($this->randoms, $r = new RandomGeneration);
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
				if ($transaction->getAction()->getInventory() === $this->getPlayer()->getInventory()) if ($in->getBlock()->getId() !== Item::AIR) {
					$r->addBlockByItem($in, $in->getCount());
					$this->editRandom($id, $m);
					return;
				}
				$nbt = $out->getNamedTagEntry('IslandArchitect');
				if ($nbt !== null) $nbt = $nbt->getByte('action');
				if ($nbt !== null) switch ($nbt) {
					case self::INVMENU_ITEM_REMOVE:
						if (!isset($this->invmenu_selected)) break;
						$r->removeBlockByItem($this->invmenu_selected);
						$this->invmenu_selected = null;
						$this->editRandom($id, $m);
						break;

					case self::INVMENU_ITEM_LUCK:
						if (!isset($this->invmenu_selected)) break;
						$r->addBlockByItem($this->invmenu_selected);
						$this->editRandom($id, $m);
						break;

					case self::INVMENU_ITEM_UNLUCK:
						if (!isset($this->invmenu_selected)) break;
						$r->removeBlockByItem($this->invmenu_selected, 1);
						break;

					case self::INVMENU_ITEM_PREVIOUS:
						if ($this->invmenu_display <= 0) break;
						$this->invmenu_display -= 33;
						$this->editRandom($id, $m, false);
						break;

					case self::INVMENU_ITEM_NEXT:
						$totalitem = 0;
						if (!$this->invmenu_collapse) foreach ($r->getAllRandomBlocks() as $chance) $totalitem += $chance;
						else $totalitem = count($r->getAllRandomBlocks());
						if (($this->invmenu_display + 33) / 33 >= (int)ceil($totalitem / 33)) break;
						$this->invmenu_display += 33;
						$this->editRandom($id, $m, false);
						break;

					case self::INVMENU_ITEM_SEED:
						$this->invmenu_item_lock = true;
						$this->getPlayer()->removeWindow($inv);
						$this->invmenu_item_lock = false;
						$this->getPlayer()->sendMessage(TF::YELLOW . 'Pleas enter a seed (Wait 10 second to cancel)');;
						$task = IslandArchitect::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $ct) : void {
							if ($this->invmenu_seed_lock !== null) {
								$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'No respond for too long (Timeout)');
								$this->editRandom($this->invmenu_seed_lock[0], $this->invmenu_seed_lock[1]);
								$this->invmenu_seed_lock[1]->send($this->getPlayer());
							}
							$this->invmenu_seed_lock = null;
						}), 20 * 10);
						$this->invmenu_seed_lock = [$id, $m, $task];
						break;

					case self::INVMENU_ITEM_ROLL:
						$i = $r->randomBlock($this->invmenu_random);
						if ($i->getBlock()->getId() === Item::AIR) return;
						$i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
						$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
						$inv->setItem(44, $i);
						break;

					case self::INVMENU_ITEM_COLLAPSE:
						$this->invmenu_collapse = !$this->invmenu_collapse;
						$this->editRandom($id, $m, false);
						break;

				} else {
					if ($out->getId() !== Item::AIR) {
						if (!isset($this->invmenu_selected)) $this->invmenu_selected = clone $out;
						else $this->invmenu_selected = null;
						$this->editRandom($id, $m, false);
					}
				}
			}));
			$m->setInventoryCloseListener(function(Player $p, Inventory $inv) use ($id, $r) : void {
				if (!$this->invmenu_item_lock) self::giveRandomGenerationBlock($this->getPlayer(), $r);
			});
		} else $m = $menu;
		$inv = $m->getInventory();
		$m->setName(TF::DARK_BLUE . 'Random regex ' . TF::BOLD . '#' . $id . (isset($this->invmenu_selected) ? ' (Selected ' . $this->invmenu_selected->getId() . ':' . $this->invmenu_selected->getDamage() . ')' : ''));
		$totalchance = 0;
		foreach ($r->getAllRandomBlocks() as $chance) $totalchance += $chance;
		foreach ($r->getAllRandomBlocks() as $block => $chance) for ($i=0; $i < (!$this->invmenu_collapse ? max((int)$chance, 1) : 1); $i++) {
			if (++$ti <= $this->invmenu_display) continue;
			if (++$ti > ($this->invmenu_display + 33)) continue;
			$block = explode(':', $block);
			$item = Item::get((int)$block[0], (int)($block[1]));
			$selected = false;
			if (isset($this->invmenu_selected)) $selected = $item->equals($this->invmenu_selected);
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
			$inv->setItem($ti - 1, $item, false);
		}

		$i = Item::get(Item::INVISIBLEBEDROCK);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		foreach ([24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 42] as $slot) $inv->setItem($slot + 9, $i, false);

		$prefix = TF::RESET . TF::BOLD . TF::GRAY;
		$surfix = "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Please select a block first)';
		$i = Item::get(Item::CONCRETE, 7);
		$i->setCustomName($prefix . 'Remove' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_REMOVE)]));
		$inv->setItem(36 + 9, $i, false);

		/**
		 * @todo Disable this action item if the chance of a block is over or equals 32767
		 */
		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Increase chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_LUCK)]));
		$inv->setItem(37 + 9, $i, false);

		$i = Item::get(Item::STONE);
		$i->setCustomName($prefix . 'Decrease chance' . $surfix);
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_UNLUCK)]));
		$inv->setItem(38 + 9, $i, false);

		$i = Item::get(($this->invmenu_display > 33 ? Item::EMPTYMAP : Item::PAPER), 0, (int)ceil(($this->invmenu_display + 33) / 33));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Previous page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_PREVIOUS)]));
		$inv->setItem(43 + 9, $i, false);

		$tdi = !$this->invmenu_collapse ? $totalchance : count($r->getAllRandomBlocks()); // Total display item
		$i = $i = Item::get($tdi / 33 < 1 ? Item::PAPER : Item::EMPTYMAP, max((int)ceil($tdi / 33) - 1, 1));
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next page');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_NEXT)]));
		$inv->setItem(44 + 9, $i, false);

		$i = Item::get(Item::SEEDS);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . (int)(($this->invmenu_random ?? $this->invmenu_random = new Random(random_int(INT32_MIN, INT32_MAX)))->getSeed()) . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to edit seed or reset random)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_SEED)]));
		$inv->setItem(39 + 9, $i, false);

		$i = Item::get(Item::EXPERIENCE_BOTTLE, 0, $roll_next ? ++$this->invmenu_random_rolled_times : $this->invmenu_random_rolled_times);
		$i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_ROLL)]));
		$inv->setItem(40 + 9, $i, false);
		
		if ($roll_next) {
			$i = $r->randomBlock($this->invmenu_random);
			if ($i->getBlock()->getId() === Item::AIR) {
				$i = Item::get(Item::END_PORTAL);
				$i->setCustomName(TF::GRAY . '(No random output)');
			} else $i->setCustomName(TF::RESET . $i->getVanillaName() . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(Random result)');
			$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
			$inv->setItem(41 + 9, $i, false);
		}

		if (!isset($this->invmenu_selected)) {
			$i = Item::get(Item::END_PORTAL);
			$i->setCustomName(TF::GRAY . '(No selected block)');
		} else {
			$i = clone $this->invmenu_selected;
			$i->setCustomName(TF::RESET . TF::YELLOW . 'Selected block: ' . TF::BOLD . TF::GOLD . $i->getVanillaName());
		}
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', -1)]));
		$inv->setItem(34 + 9, $i, false);

		$i = Item::get(Item::SHULKER_BOX, $this->invmenu_collapse ? 14 : 5);
		$i->setCustomName(TF::RESET . TF::YELLOW . 'Show chance as block (Expand mode): ' . TF::BOLD . ($this->invmenu_collapse ? TF::RED . 'Off' : TF::GREEN . 'On') . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(Click / drop to toggle)');
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new ByteTag('action', self::INVMENU_ITEM_COLLAPSE)]));
		$inv->setItem(35 + 9, $i, false);

		$inv->sendContents($inv->getViewers());
	}

	/**
	 * @var array<int, InvMenu, TaskHandler>|null
	 */
	private $invmenu_seed_lock = null;

	public function onPlayerChat(PlayerChatEvent $ev) : void {
		if (!isset($this->invmenu_seed_lock)) return;
		if (!$ev->getPlayer() === $this->getPlayer()) return;
		$ev->setCancelled();
		$this->invmenu_seed_lock[2]->cancel();
		$msg = $ev->getMessage();
		$this->invmenu_random = new Random(empty(preg_replace('/[0-9-]+/i', '', $msg)) ? (int)$msg : TemplateIslandGenerator::convertSeed($msg));
		$this->editRandom($this->invmenu_seed_lock[0], $this->invmenu_seed_lock[1]);
		$this->invmenu_seed_lock = null;
	}

	public function onBlockPlace(BlockPlaceEvent $ev) : void {
		if ($ev->getPlayer() !== $this->getPlayer()) return;
		if (($nbt = $ev->getItem()->getNamedTagEntry('IslandArchitect')) === null) return;
		if (($nbt = $nbt->getCompoundTag('random-generation')) === null) return;
		if (($nbt = $nbt->getListTag('regex')) === null) return;
		$tile = RandomGenerationTile::createTile('RandomGenerationTile', $this->getPlayer()->getLevel(), RandomGenerationTile::createNBT($ev->getBlock()->asVector3(), null, $ev->getItem()));
	}

	/**
	 * @var int Unix timestamp
	 */
	private $invmenu_interact_lock = 0;

	public function onPlayerInteract(PlayerInteractEvent $ev) : void {
		if ($ev->getPlayer() !== $this->getPlayer()) return;
		if (time() <= $this->invmenu_interact_lock + 10) return;
		$this->invmenu_interact_lock = time();
		if (!($tile = $ev->getBlock()->getLevel()->getTile($ev->getBlock()->asVector3())) instanceof RandomGenerationTile) return;
		$ev->getBlock()->getLevel()->setBlock($ev->getBlock()->asVector3(), Block::get(Block::AIR));
		$this->editRandom(array_push($this->randoms, $tile->getRandomGeneration()));
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
			$block = explode($block);
			$bi = Item::get((int)$block[0], (int)($block[1] ?? 0));
			$blockslore[] = $bi->getName() . ' (' . $bi->getId() . ':' . $bi->getDamage() . '): ' . TF::BOLD . TF::GREEN . $chance . TF::ITALIC . ' (' . round((int)$chance / ($totalchance ?? (int)$chance) * 100, 2) . '%)';
		}
		$i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . 'Random generation' . (!empty($blockslore ?? []) ? ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $blockslore ?? []) : ''));
		$i->setNamedTagEntry(new CompoundTag('IslandArchitect', [new CompoundTag('random-generation', [
			new ListTag('regex', $regex ?? [])
		])]));
		$inv->addItem($i);
	}

}