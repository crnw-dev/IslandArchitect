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

namespace Clouria\IslandArchitect\worldedit\buildertools;

use pocketmine\{
    block\Block,
    level\Position,
    math\Vector3,
    nbt\tag\CompoundTag,
    nbt\tag\IntTag,
    nbt\tag\ListTag,
    Player};

use czechpmdevs\buildertools\{
    editors\Printer,
    math\BlockGenerator,
    math\Math};

use Clouria\IslandArchitect\{
    customized\CustomizableClassTrait,
    customized\GetPrivateMethodClosureTrait,
    events\RandomGenerationBlockPaintEvent,
    IslandArchitect,
    runtime\RandomGeneration};

class CustomPrinter extends Printer {
    use CustomizableClassTrait, GetPrivateMethodClosureTrait;

        public function draw(Player $player, Position $center, Block $block, int $brush = 4, int $mode = 0x00, bool $throwBlock = false) : void {
        parent::draw($player, $center, $block, $brush, $mode, $throwBlock);

        $item = $player->getInventory()->getItemInHand();
		if (!($nbt = $item->getNamedTagEntry('IslandArchitect')) instanceof CompoundTag) return;
		if (!($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag) return;
		if (!($regex = $nbt->getTag('regex', ListTag::class)) instanceof ListTag) return;
		$s = IslandArchitect::getInstance()->getSession($player);
		if ($s::errorCheckOutRequired($s->getPlayer(), $s)) return;
		$regex = RandomGeneration::fromNBT($regex);

        // $undoList = new BlockArray();
        // TODO: Allow removing random generation blocks with the undo command

        // $undoList->setLevel($center->getLevel());
        $center = Math::ceilPosition($center);

        $level = $center->getLevelNonNull();

        $placeBlock = function (Vector3 $vector3) use ($level, /*$undoList, */$block, $center, $throwBlock) {
            if($throwBlock) {
                $vector3 = $this->getPrivateMethodClosure('throwBlock')(Position::fromObject($vector3, $center->getLevel()), $block);
            }
            if($vector3->getY() < 0) {
                return;
            }

            $fullBlock = $level->getBlock($vector3);
            // $undoList->addBlock($vector3, $fullBlock->getId(), $fullBlock->getDamage());

            $array[] = $vector3;
        };

        if($mode == self::CUBE) {
            foreach (BlockGenerator::generateCube($brush) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        } elseif($mode == self::SPHERE) {
            foreach (BlockGenerator::generateSphere($brush) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        } elseif($mode == self::CYLINDER) {
            foreach (BlockGenerator::generateCylinder($brush, $brush) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        } elseif($mode == self::HOLLOW_CUBE) {
            foreach (BlockGenerator::generateCube($brush, true) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        } elseif($mode == self::HOLLOW_SPHERE) {
            foreach (BlockGenerator::generateSphere($brush, true) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        } elseif($mode == self::HOLLOW_CYLINDER) {
            foreach (BlockGenerator::generateCylinder($brush, $brush,true) as [$x, $y, $z]) {
                $placeBlock($center->add($x, $y, $z));
            }
        }

        // Canceller::getInstance()->addStep($player, $undoList);
        $e = new RandomGenerationBlockPaintEvent($s, $regex, $array ?? [5], $item); // TODO
		$e->call();
		if ($e->isCancelled()) return;
		if (!($regexid = $nbt->getTag('regexid', IntTag::class)) instanceof IntTag) {
		    foreach ($s->getIsland()->getRandoms() as $i => $sr) if ($sr->equals($regex)) $regexid = $i;
		    if ($regexid === null) $regexid = $s->getIsland()->addRandom($regex);
        }
		foreach ($e->getBlocks() as $vec) $s->getIsland()->setBlockRandom($vec, $regexid);
    }

}