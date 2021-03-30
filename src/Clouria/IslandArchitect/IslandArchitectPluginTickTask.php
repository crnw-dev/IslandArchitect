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


use Clouria\IslandArchitect\customized\CustomizableClassTrait;
use pocketmine\{
    level\Level,
    math\Vector3,
    scheduler\Task,
    math\AxisAlignedBB,
    utils\TextFormat as TF,
    level\particle\RedstoneParticle};

class IslandArchitectPluginTickTask extends Task {
    use CustomizableClassTrait;

    public function onRun(int $ct) : void {
        foreach (IslandArchitect::getInstance()->getSessions() as $s) if (($is = $s->getIsland()) !== null) {
            $sb = $s->getPlayer()->getTargetBlock(12);
            $sc = $is->getStartCoord();
            $ec = $is->getEndCoord();

            // Send random generation block popup
            $r = $is->getRandomByVector3($sb);
            if ($r !== null) $s->getPlayer()->sendPopup(TF::YELLOW . 'Random generation block: ' . TF::BOLD .
                TF::GOLD . $is->getRandomLabel($r));

            // Island chest coord popup
            $chest = $is->getChest();
            if ($chest !== null and $chest->asVector3()->equals($sb->asVector3())) $s->getPlayer()->sendPopup(TF::YELLOW . 'Island chest block'/* . "\n" . TF::ITALIC . TF::GRAY . '(Click to view or edit contents)'*/);

            $distance = $s->getPlayer()->getViewDistance();
            $dbb = (new AxisAlignedBB(
                ($s->getPlayer()->getFloorX() >> 4) - $distance,
                0,
                ($s->getPlayer()->getFloorZ() >> 4) - $distance,
                ($s->getPlayer()->getFloorX() >> 4) + $distance,
                Level::Y_MAX,
                ($s->getPlayer()->getFloorZ() >> 4) + $distance
            ))->expand(1, 1, 1);

            // Island spawn floating text
            $spawn = $is->getSpawn();
            if (
                $spawn === null or $is->getLevel() !== $s->getPlayer()->getLevel()->getFolderName() or
                !$dbb->isVectorInside(new Vector3((int)$spawn->getFloorX() >> 4, (int)$spawn->getFloorY() >> 4, (int)
                    $spawn->getFloorZ() >>
                    4))
            ) $s->hideFloatingText($s::FLOATINGTEXT_SPAWN);
            else $s->showFloatingText($s::FLOATINGTEXT_SPAWN);

            // Draw island area outline
            if (
                $sc !== null and
                $ec !== null and
                $s->getPlayer()->getLevel()->getFolderName() === $is->getLevel() and
                (bool)IslandArchitect::getInstance()->getConfig()->get('enable-particles')
            ) {
                $bb = new AxisAlignedBB(
                    min($sc->getFloorX(), $ec->getFloorX()),
                    min($sc->getFloorY(), $ec->getFloorY()),
                    min($sc->getFloorZ(), $ec->getFloorZ()),
                    max($sc->getFloorX(), $ec->getFloorX()),
                    max($sc->getFloorY(), $ec->getFloorY()),
                    max($sc->getFloorZ(), $ec->getFloorZ())
                );
                $bb->offset(0.5, 0.5, 0.5);
                $bb->expand(0.5, 0.5, 0.5);
                for ($x = $bb->minX; $x <= $bb->maxX; ++$x)
                for ($y = $bb->minY; $y <= $bb->maxY; ++$y)
                for ($z = $bb->minZ; $z <= $bb->maxZ; ++$z) {
                    if (!$dbb->isVectorInside(new Vector3((int)$x >> 4, (int)$y >> 4, (int)$z >> 4))) continue;
                    if (
                        $x !== $bb->minX and
                        $y !== $bb->minY and
                        $z !== $bb->minZ and
                        $x !== $bb->maxX and
                        $y !== $bb->maxY and
                        $z !== $bb->maxZ
                    ) continue;
                    $s->getPlayer()->getLevel()->addParticle(new RedstoneParticle(new Vector3($x, $y, $z), 10),
                        [$s->getPlayer()]);
                }
            }
        }
    }

}