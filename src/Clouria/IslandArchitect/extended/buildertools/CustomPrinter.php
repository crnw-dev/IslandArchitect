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

namespace Clouria\IslandArchitect\extended\buildertools;

use Clouria\IslandArchitect\sessions\PlayerSession;
use czechpmdevs\buildertools\{
    utils\Math,
    editors\Printer
};
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use Clouria\IslandArchitect\{
    IslandArchitect,
    events\RandomGenerationBlockUpdateEvent
};
use pocketmine\{
    Player,
    block\Block,
    math\Vector3,
    nbt\tag\IntTag,
    level\Position,
    nbt\tag\ListTag,
    nbt\tag\CompoundTag,
    utils\TextFormat as TF
};

class CustomPrinter extends Printer {

    public function draw(Player $player, Position $center, Block $block, int $brush = 4, int $mode = 0x00, bool $fall = false) {
        $item = $player->getInventory()->getItemInHand();
        if (
            !($nbt = $item->getNamedTagEntry('IslandArchitect')) instanceof CompoundTag or
            !($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag or
            !($regex = $nbt->getTag('regex', ListTag::class)) instanceof ListTag
        ) {
            parent::draw($player, $center, $block, $brush, $mode, $fall);
            return;
        }
        $s = IslandArchitect::getInstance()->getSession($player);
        if (PlayerSession::errorCheckOutRequired($player, $s)) return;
        if ($s->getIsland()->getLevel() !== $player->getLevel()->getFolderName()) {
            $player->sendMessage(TF::BOLD . TF::RED . 'You can only place random generation blocks in the same world as the island: ' . $s->getIsland()->getLevel());
            return;
        } else $s->getIsland()->setLevel($player->getLevel()->getFolderName());

        parent::draw($player, $center, $block, $brush, $mode, $fall);

        $s = IslandArchitect::getInstance()->getSession($player);
        $regex = RandomGeneration::fromNBT($regex);

        // TODO: Allow removing random generation blocks with the undo command
        // $undoList = new BlockList;

        // Fun fact undo printer is not implemented in BuilderTools 1.2.0-beta2

        $center = Math::roundPosition($center);
        switch ($mode) {
            case self::CUBE:
                for ($x = $center->getX() - $brush; $x <= $center->getX() + $brush; $x++)
                    for ($y = $center->getY() - $brush; $y <= $center->getY() + $brush; $y++)
                        for ($z = $center->getZ() - $brush; $z <= $center->getZ() + $brush; $z++) {
                            if (!$fall) {
                                if ($y > 0) {
                                    $array[] = new Vector3($x, $y, $z);
                                    // $undoList->addBlock(new Vector3($x, $y, $z), $block);
                                }
                            }/* else {
                        $finalPos = $this->throwBlock(new Position($x, $y, $z, $center->getLevel()), $block);
                        $undoList->addBlock($finalPos, $block);
                    }*/
                        }
                break;

            case self::SPHERE:
                for ($x = $center->getX()-$brush; $x <= $center->getX()+$brush; $x++) {
                    $xsqr = ($center->getX()-$x) * ($center->getX()-$x);
                    for ($y = $center->getY()-$brush; $y <= $center->getY()+$brush; $y++) {
                        $ysqr = ($center->getY()-$y) * ($center->getY()-$y);
                        for ($z = $center->getZ()-$brush; $z <= $center->getZ()+$brush; $z++) {
                            $zsqr = ($center->getZ()-$z) * ($center->getZ()-$z);
                            if(($xsqr + $ysqr + $zsqr) <= ($brush*$brush)) {
                                if(!$fall) {
                                    if($y > 0) {
                                        $array[] = new Vector3($x, $y, $z);
                                        // $undoList->addBlock(new Vector3($x, $y, $z), $block);
                                    }
                                }/* else {
                                    $finalPos = $this->throwBlock(new Position($x, $y, $z, $center->getLevel()), $block);
                                    $undoList->addBlock($finalPos, $block);
                                }*/
                            }
                        }
                    }
                }
                break;
        }

        $e = new RandomGenerationBlockUpdateEvent($s, $regex, $array ?? [], $item, null, RandomGenerationBlockUpdateEvent::PAINT);
        $e->call();
        if ($e->isCancelled()) return;
        if (!($regexid = $nbt->getTag('regexid', IntTag::class)) instanceof IntTag) {
            foreach ($s->getIsland()->getRandoms() as $i => $sr) if ($sr->equals($regex)) $regexid = $i;
            if ($regexid === null) $regexid = $s->getIsland()->addRandom($regex);
        }
        foreach ($e->getPosition() as $vec) $s->getIsland()->setBlockRandom($vec, $regexid);
    }

}