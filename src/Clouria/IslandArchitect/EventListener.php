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
    utils\TextFormat as TF
};
use room17\SkyBlock\SkyBlock;
use czechpmdevs\buildertools\BuilderTools;
use pocketmine\nbt\tag\{
    IntTag,
    ListTag,
    CompoundTag};
use Clouria\IslandArchitect\{
    runtime\RandomGeneration,
    runtime\sessions\PlayerSession,
    events\RandomGenerationBlockBreakEvent,
    events\RandomGenerationBlockPlaceEvent
};
use pocketmine\event\{
    Listener,
    level\LevelSaveEvent,
    block\BlockPlaceEvent,
    block\BlockBreakEvent,
    player\PlayerQuitEvent,
    plugin\PluginEnableEvent,
    entity\EntityExplodeEvent,
    inventory\InventoryOpenEvent};
use function class_exists;

class EventListener implements Listener {

    /**
     * @var bool
     */
    private $disabled = false;

    public function disableListener() : bool {
        if ($this->disabled) return false;
        $this->disabled = true;
        return true;
    }

    /**
     * @return bool
     */
    public function isDisabled() : bool {
        return $this->disabled;
    }

    /**
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $ev) : void {
        if ($this->isDisabled()) return;
        if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null) return;
        IslandArchitect::getInstance()->disposeSession($s);
    }

    /**
     * @priority HIGH
     * @ignoreCancelled
     */
    public function onBlockBreak(BlockBreakEvent $ev) : void {
        if ($this->isDisabled()) return;
        if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null or $s->getIsland() === null) return;

        $vec = $ev->getBlock()->asVector3();
        if (($r = $s->getIsland()->getRandomByVector3($vec)) === null) return;
        $e = new RandomGenerationBlockBreakEvent($s, $r, $ev->getBlock()->asPosition());
        $e->call();
        if ($e->isCancelled()) return;
        $s->getPlayer()->sendPopup(TF::BOLD . TF::YELLOW . 'You have destroyed a random generation block, ' . TF::GOLD . 'the item has returned to your inventory!');
        $regex = $s->getIsland()->getRandomById($r);
        $nbt = $s->getPlayer()->getInventory()->getItemInHand()->getNamedTagEntry('IslandArchitect');
        if (
            !$nbt instanceof CompoundTag or
            !($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag or
            !($nbt = $nbt->getTag('regex', ListTag::class)) instanceof ListTag or
            !RandomGeneration::fromNBT($nbt)->equals($regex)
        ) {
            $i = $regex->getRandomGenerationItem($s->getIsland()->getRandomSymbolicItem($r));
            $i->setCount(64);
            $s->getPlayer()->getInventory()->addItem($i);
        }
    }

    /**
     * @priority HIGH
     * @ignoreCancelled
     */
    public function onBlockPlace(BlockPlaceEvent $ev) : void {
        if ($this->isDisabled()) return;
        $s = IslandArchitect::getInstance()->getSession($ev->getPlayer());
        $item = $ev->getItem();
        if (!($nbt = $item->getNamedTagEntry('IslandArchitect')) instanceof CompoundTag) return;
        if (!($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag) return;
        if (!($regex = $nbt->getTag('regex', ListTag::class)) instanceof ListTag) return;
        if (PlayerSession::errorCheckOutRequired($ev->getPlayer(), $s)) return;
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
        ) $regexid = $s->getIsland()->addRandom($regex);
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
        if ($this->isDisabled()) return;
        foreach (IslandArchitect::getInstance()->getSessions() as $s) $s->saveIsland();
    }

    /**
     * @priority MONITOR
     */
    public function onEntityExplode(EntityExplodeEvent $ev) : void {
        if ($this->isDisabled()) return;
        foreach (IslandArchitect::getInstance()->getSessions() as $s) {
            $island = $s->getIsland();
            if ($island === null) continue;
            $affected = 0;
            foreach ($ev->getBlockList() as $block) if ($island->getRandomByVector3($block->asVector3()) !== null) {
                $affected++;
                $s->getIsland()->setBlockRandom($block->asVector3(), null);
            }
            $pos = $ev->getPosition();
            if ($affected > 0) $s->getPlayer()
                                 ->sendMessage(TF::YELLOW . 'An explosion has destroyed ' . TF::BOLD . TF::GOLD . $affected . TF::RESET . TF::YELLOW . ' random generation blocks at ' . TF::BOLD . TF::GREEN . $pos->getFloorX() . ', ' . $pos->getFloorY() . ', ' . $pos->getFloorZ());
        }
    }

    /**
     * @priority HIGH
     */
    public function onPluginEnable(PluginEnableEvent $ev) : void {
        if ($this->isDisabled()) return;
        $pl = $ev->getPlugin();
        if (class_exists(SkyBlock::class) and $pl instanceof SkyBlock) IslandArchitect::getInstance()->initDependency($pl);
        if (class_exists(BuilderTools::class) and $pl instanceof BuilderTools) IslandArchitect::getInstance()->initDependency($pl);
    }

    /**
     * @priority MONITOR
     */
    public function onInventoryOpen(InventoryOpenEvent $ev) : void {
    }
}