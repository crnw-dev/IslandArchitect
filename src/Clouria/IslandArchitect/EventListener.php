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

namespace Clouria\IslandArchitect;

use pocketmine\{
    level\Position,
    utils\TextFormat as TF
};
use pocketmine\nbt\tag\{
    CompoundTag,
    IntTag,
    ListTag
};
use pocketmine\event\{
    block\BlockBreakEvent,
    block\BlockPlaceEvent,
    entity\EntityExplodeEvent,
    inventory\InventoryOpenEvent,
    level\ChunkLoadEvent,
    level\LevelSaveEvent,
    Listener,
    player\PlayerQuitEvent,
    plugin\PluginEnableEvent};

use room17\SkyBlock\SkyBlock;

use Clouria\IslandArchitect\{
    customized\CustomizableClassTrait,
    runtime\RandomGeneration,
    events\RandomGenerationBlockPlaceEvent,
    runtime\sessions\IslandChestSession};
use pocketmine\inventory\ChestInventory;
use function assert;

class EventListener implements Listener {
    use CustomizableClassTrait;

    /**
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $ev) : void {
		if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null) return;
		IslandArchitect::getInstance()->disposeSession($s);
	}

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onBlockBreak(BlockBreakEvent $ev) : void {
		if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null or $s->getIsland() === null) return;

		$vec = $ev->getBlock()->asVector3();
		if (($r = $s->getIsland()->getRandomByVector3($vec)) === null) return;
		$s->getPlayer()->sendPopup(TF::BOLD . TF::YELLOW . 'You have destroyed a random generation block, ' . TF::GOLD . 'the item has returned to your inventory!');
		$i = $s->getIsland()->getRandomById($r)->getRandomGenerationItem($s->getIsland()->getRandomSymbolicItem($r), $r);
		$i->setCount(64);
		$s->getPlayer()->getInventory()->addItem($i);
	}

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $ev) : void {
		if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null or $s->getIsland() === null) return;

		$item = $ev->getItem();
		if (!($nbt = $item->getNamedTagEntry('IslandArchitect')) instanceof CompoundTag) return;
		if (!($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag) return;
		if (!($regex = $nbt->getTag('regex', ListTag::class)) instanceof ListTag) return;
		if ($s::errorCheckOutRequired($s->getPlayer(), $s)) return;
		$regex = RandomGeneration::fromNBT($regex);
		$e = new RandomGenerationBlockPlaceEvent($s, $regex, $ev->getBlock()->asPosition(), $item);
		$e->call();
		if ($e->isCancelled()) return;
		if (!($regexid = $nbt->getTag('regexid', IntTag::class)) instanceof IntTag) {
		    foreach ($s->getIsland()->getRandoms() as $i => $sr) if ($sr->equals($regex)) $regexid = $i;
		    if ($regexid === null) $regexid = $s->getIsland()->addRandom($regex);
        }
		if (
		    $regexid instanceof IntTag and
			(($r = $s->getIsland()->getRandomById($regexid = $regexid->getValue())) === null or
			!$r->equals($regex))
		) $regexid = $s->getIsland()->addRandom($r = $regex);
		$s->getIsland()->setBlockRandom($ev->getBlock()->asVector3(), $regexid, $e);
		$symbolic = $s->getIsland()->getRandomSymbolicItem($regexid);
		$item = clone $item;
		if (!$item->equals($symbolic, true, false)) {
			$nbt = $item->getNamedTag();
			$item = $symbolic;
			foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
			$ev->setCancelled();
			$ev->getBlock()->getLevel()->setBlock($ev->getBlock()->asVector3(), $item->getBlock());
		}
		$item->setCount(64);
		$s->getPlayer()->getInventory()->setItemInHand($item);
	}

	/**
	 * @priority MONITOR
	 */
	public function onLevelSave(LevelSaveEvent $ev) : void {
		foreach (IslandArchitect::getInstance()->getSessions() as $s) $s->saveIsland();
	}

    /**
     * @priority MONITOR
     */
	public function onEntityExplode(EntityExplodeEvent $ev) : void {
	    foreach (IslandArchitect::getInstance()->getSessions() as $s) {
	        $island = $s->getIsland();
	        if ($island === null) continue;
	        $affected = 0;
            foreach ($ev->getBlockList() as $block) if (($r = $island->getRandomByVector3($block->asVector3())) !== null) {
                $affected++;
                $s->getIsland()->setBlockRandom($block->asVector3(), null);
            }
            $pos = $ev->getPosition();
            if ($affected > 0) $s->getPlayer()->sendMessage(TF::YELLOW . 'An explosion has destroyed ' . TF::BOLD . TF::GOLD . $affected . TF::RESET . TF::YELLOW . ' random generation blocks at ' . TF::BOLD . TF::GREEN . $pos->getFloorX() . ', ' . $pos->getFloorY() . ', ' . $pos->getFloorZ());
        }
    }

    /**
	 * @priority HIGH
	 */
	public function onPluginEnable(PluginEnableEvent $ev) : void {
	    $pl = $ev->getPlugin();
	    if (!$pl instanceof SkyBlock) return;
	    IslandArchitect::getInstance()->initDependency();
    }

    /**
     * @priority MONITOR
     */
    public function onChunkLoad(ChunkLoadEvent $ev) : void {
	    if ($ev->isNewChunk()) IslandArchitect::getInstance()->createIslandChest($ev->getLevel(), $ev->getChunk());
    }

    /**
     * @priority MONITOR
     */
    public function onInventoryOpen(InventoryOpenEvent $ev) : void {
        if (!$ev->getInventory() instanceof ChestInventory) return;
        $pos = $ev->getInventory()->getHolder()->asPosition();
        assert($pos instanceof Position);
        foreach (IslandArchitect::getInstance()->getSessions() as $s) {
            $is = $s->getIsland();
            if (
                $is === null or
                $is->getLevel() !== $pos->getLevel()->getFolderName() or
                $is->getChest() === null or
                !$is->getChest()->equals($pos->asVector3())
            ) return;
            new IslandChestSession($s);
            $ev->setCancelled();
        }
    }
}