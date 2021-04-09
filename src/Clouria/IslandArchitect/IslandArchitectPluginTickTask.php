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
    level\Level,
    math\Vector3,
    scheduler\Task,
    math\AxisAlignedBB,
    utils\SingletonTrait,
    utils\TextFormat as TF
};

class IslandArchitectPluginTickTask extends Task {
    use SingletonTrait;

    public const PERIOD = 10;

    public function onRun(int $currentTick) : void {
        foreach (IslandArchitect::getInstance()->getSessions() as $s) if (($is = $s->getIsland()) !== null) {
            $sb = $s->getPlayer()->getTargetBlock(12);
            $sc = $is->getStartCoord();
            $ec = $is->getEndCoord();

            // Send random generation block popup
            $r = $is->getRandomByVector3($sb);
            if ($r !== null) $s->getPlayer()->sendPopup(TF::YELLOW . 'Random generation block: ' . TF::BOLD .
                TF::GOLD . $is->getRandomLabel($r));

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
                !$dbb->isVectorInside(new Vector3($spawn->getFloorX() >> 4, $spawn->getFloorY() >> 4, $spawn->getFloorZ() >>
                    4))
            ) $s->hideFloatingText($s::FLOATINGTEXT_SPAWN);
            else $s->showFloatingText($s::FLOATINGTEXT_SPAWN);

            // Island start coord floating text
            if (
                $sc === null or $is->getLevel() !== $s->getPlayer()->getLevel()->getFolderName() or
                !$dbb->isVectorInside(new Vector3($sc->getFloorX() >> 4, $sc->getFloorY() >> 4, $sc->getFloorZ() >>
                    4))
            ) $s->hideFloatingText($s::FLOATINGTEXT_STARTCOORD);
            else $s->showFloatingText($s::FLOATINGTEXT_STARTCOORD);

            // Island end coord floating text
            if (
                $ec === null or $is->getLevel() !== $s->getPlayer()->getLevel()->getFolderName() or
                !$dbb->isVectorInside(new Vector3($ec->getFloorX() >> 4, $ec->getFloorY() >> 4, $ec->getFloorZ() >>
                    4))
            ) $s->hideFloatingText($s::FLOATINGTEXT_ENDCOORD);
            else $s->showFloatingText($s::FLOATINGTEXT_ENDCOORD);
        }
    }

}