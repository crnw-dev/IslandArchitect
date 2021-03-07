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

use pocketmine\utils\TextFormat as TF;
use pocketmine\nbt\tag\{
    CompoundTag,
    IntTag,
    ListTag
};
use pocketmine\event\{
    block\BlockBreakEvent,
    block\BlockPlaceEvent,
    entity\EntityExplodeEvent,
    level\LevelSaveEvent,
    Listener,
    player\PlayerQuitEvent,
    plugin\PluginEnableEvent
};

use room17\SkyBlock\SkyBlock;

use Clouria\IslandArchitect\{
    customized\CustomizableClassTrait,
    customized\skyblock\CustomSkyBlockCreateCommand,
    runtime\RandomGeneration,
    events\RandomGenerationBlockPlaceEvent
};

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
		$s = IslandArchitect::getInstance()->getSession($ev->getPlayer());

		$vec = $ev->getBlock()->asVector3();
		if (($r = $s->getIsland()->getRandomByVector3($vec)) === null) return;
		$s->getPlayer()->sendPopup(TF::BOLD . TF::YELLOW . 'You have destroyed a random generation block, ' . TF::GOLD . 'the item has returned to your inventory!');
		$i = $s->getIsland()->getRandomById($r)->getRandomGenerationItem($s->getIsland()->getRandomSymbolicItem($r));
		$i->setCount(64);
		$s->getPlayer()->getInventory()->addItem($i);
	}

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $ev) : void {
		if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null) return;

		$item = $ev->getItem();
		if (($nbt = $item->getNamedTagEntry('IslandArchitect')) === null) return;
		if (($nbt = $nbt->getTag('random-generation', CompoundTag::class)) === null) return;
		if (($regex = $nbt->getTag('regex', ListTag::class)) === null) return;
		if ($s::errorCheckOutRequired($s->getPlayer(), $s)) return;
		$regex = RandomGeneration::fromNBT($regex);
		$e = new RandomGenerationBlockPlaceEvent($s, $regex, $ev->getBlock()->asPosition(), $item);
		$e->call();
		if ($e->isCancelled()) return;
		if (
			($regexid = $nbt->getTag('regexid', IntTag::class)) === null or
			($r = $s->getIsland()->getRandomById($regexid = $regexid->getValue())) === null or
			!$r->equals($regex)
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
	        if ($s->getIsland() === null) continue;
	        $randomblocks = $s->getIsland()->getRandomBlocks();
            foreach ($ev->getBlockList() as $block) if (($randomblocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] ?? null) !== null) $s->getIsland()->setBlockRandom($block->asVector3(), null);
        }
    }

    /**
	 * @priority HIGH
	 */
	public function onPluginEnable(PluginEnableEvent $ev) : void {
	    $pl = $ev->getPlugin();
	    if (!$pl instanceof SkyBlock) return;
	    $map = $pl->getCommandMap();
	    $cmd = $map->getCommand('create');
	    if ($cmd !== null) $pl->getCommandMap()->unregisterCommand($cmd->getName());
	    $class = CustomSkyBlockCreateCommand::getClass();
	    $map->registerCommand(new $class);
    }
}