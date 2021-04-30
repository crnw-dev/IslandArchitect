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

        ██╗  ██╗    ██╗  ██╗
        ██║  ██║    ██║ ██╔╝    光   時   LIBERATE
        ███████║    █████╔╝     復   代   HONG
        ██╔══██║    ██╔═██╗     香   革   KONG
        ██║  ██║    ██║  ██╗    港   命
        ╚═╝  ╚═╝    ╚═╝  ╚═╝

														*/
declare(strict_types=1);

namespace Clouria\IslandArchitect\internal;

use pocketmine\Server;
use pocketmine\utils\Random;
use room17\SkyBlock\SkyBlock;
use pocketmine\nbt\tag\IntTag;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as TF;
use czechpmdevs\buildertools\BuilderTools;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use Clouria\IslandArchitect\IslandArchitect;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\event\level\ChunkPopulateEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\generator\StructureGeneratorTask;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use Clouria\IslandArchitect\extended\skyblock\DummyIslandGenerator;
use Clouria\IslandArchitect\events\RandomGenerationBlockUpdateEvent;
use Clouria\IslandArchitect\extended\pocketmine\DummyWorldGenerator;
use function class_exists;

class IslandArchitectEventListener implements Listener {
    use SingletonTrait;

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
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $ev) : void {
        if ($this->isDisabled()) return;
        if (($s = IslandArchitect::getInstance()->getSession($ev->getPlayer())) === null) return;
        IslandArchitect::getInstance()->disposeSession($s);
    }

    /**
     * @return bool
     */
    public function isDisabled() : bool {
        return $this->disabled;
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
        $e = new RandomGenerationBlockUpdateEvent($s, $s->getIsland()->getRandomById($r), $ev->getBlock()->asPosition(), null, $ev, RandomGenerationBlockUpdateEvent::BREAK);
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
            $i = $regex->getRandomGenerationItem($s->getIsland()->getRandomSymbolicItem($r), $r);
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
        $e = new RandomGenerationBlockUpdateEvent($s, $regex, $ev->getBlock()->asVector3(), $item, $ev);
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
        $s->getIsland()->setBlockRandom($ev->getBlock()->asVector3(), $regexid);
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
            if ($affected > 0) $s->getPlayer()->sendMessage(TF::YELLOW . 'An explosion has destroyed ' . TF::BOLD . TF::GOLD . $affected . TF::RESET . TF::YELLOW . ' random generation blocks at ' .
                TF::BOLD . TF::GREEN . $pos->getFloorX() . ', ' . $pos->getFloorY() . ', ' . $pos->getFloorZ()
            );
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

    /**
     * @priority NORMAL
     */
    public function onQueryRegenerate(QueryRegenerateEvent $ev) : void {
        if ($this->isDisabled()) return;
        if (!(bool)IslandArchitect::getInstance()->getConfig()->get('hide-plugin-in-query', false)) return;
        if (($r = array_search($this, $pl = $ev->getPlugins())) === false) return;
        unset($pl[$r]);
        $ev->setPlugins($pl);
    }

    public function onChunkPopulate(ChunkPopulateEvent $ev) : void {
        $gen = $ev->getLevel()->getProvider()->getGenerator();
        if ($gen !== DummyWorldGenerator::GENERATOR_NAME and !(class_exists(SkyBlock::class) and DummyIslandGenerator::LEGACY_GENERATOR_NAME)) return;
        if ($ev->getChunk()->isGenerated()) return;
        Server::getInstance()->getAsyncPool()->submitTask(new StructureGeneratorTask(
            $ev->getLevel()->getProvider()->getPath() . 'isarch-structure.json',
            new Random($ev->getLevel()->getProvider()->getSeed()),
            $ev->getLevel(),
            $ev->getChunk()->getX(),
            $ev->getChunk()->getZ()
        ));
    }
}