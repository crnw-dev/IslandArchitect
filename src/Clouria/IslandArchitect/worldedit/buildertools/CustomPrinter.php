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
    Player
};

use czechpmdevs\buildertools\{
    blockstorage\BlockArray,
    BuilderTools,
    editors\Editor,
    editors\Printer,
    math\BlockGenerator,
    math\Math
};

use Clouria\IslandArchitect\IslandArchitect;

class CustomPrinter extends Printer
{
        public function draw(Player $player, Position $center, Block $block, int $brush = 4, int $mode = 0x00, bool $throwBlock = false) {
        $undoList = new BlockArray();
        $undoList->setLevel($center->getLevel());
        $center = Math::roundPosition($center);

        $placeBlock = function (Vector3 $vector3) use ($player, $undoList, $block, $center, $throwBlock) {
            if($throwBlock) {
                $reflect = new \ReflectionMethod(Printer::class, 'throwBlock');
                $reflect->setAccessible(true);
                $reflect = $reflect->getClosure($this);
                $vector3 = $reflect(Position::fromObject($vector3, $center->getLevel()), $block);
            }
            if($vector3->getY() < 0) {
                return;
            }

            $fullBlock = $center->getLevel()->getBlock($vector3);
            $undoList->addBlock($vector3, $fullBlock->getId(), $fullBlock->getDamage());
            $center->getLevel()->setBlockIdAt($vector3->getX(), $vector3->getY(), $vector3->getZ(), $block->getId());
            $center->getLevel()->setBlockDataAt($vector3->getX(), $vector3->getY(), $vector3->getZ(), $block->getDamage());

            $session = IslandArchitect::getInstance()->getSession($player);
            if ($session !== null and $session->getIsland() !== null) {
                $session->getIsland()->setBlockRandom($vector3);
                $i = $player->getInventory()->getItemInHand();
                $i = $i->getNamedTagEntry('IslandArchitect');
                /**
                 * @TODO
                 */
            }
        };

        if($mode == self::CUBE) {
            foreach (BlockGenerator::generateCube($brush) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        } elseif($mode == self::SPHERE) {
            foreach (BlockGenerator::generateSphere($brush) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        } elseif($mode == self::CYLINDER) {
            foreach (BlockGenerator::generateCylinder($brush, $brush) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        } elseif($mode == self::HOLLOW_CUBE) {
            foreach (BlockGenerator::generateCube($brush, true) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        } elseif($mode == self::HOLLOW_SPHERE) {
            foreach (BlockGenerator::generateSphere($brush, true) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        } elseif($mode == self::HOLLOW_CYLINDER) {
            foreach (BlockGenerator::generateCylinder($brush, $brush,true) as $vector3) {
                $placeBlock($center->add($vector3));
            }
        }

        /** @var Canceller $canceller */
        $canceller = BuilderTools::getEditor(Editor::CANCELLER);
        $canceller->addStep($player, $undoList);
    }

}