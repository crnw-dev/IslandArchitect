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


use pocketmine\scheduler\Task;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\IslandArchitect;

class IslandArchitectPluginTickTask extends Task {
    use SingletonTrait;

    public const PERIOD = 10;

    public function onRun(int $currentTick) : void {
        foreach (IslandArchitect::getInstance()->getSessions() as $s) if (($is = $s->getIsland()) !== null) {
            $sb = $s->getPlayer()->getTargetBlock(12);

            // Send random generation block popup
            $r = $is->getRandomByVector3($sb);
            if ($r !== null) $s->getPlayer()->sendPopup(TF::YELLOW . 'Random generation block: ' . TF::BOLD .
                TF::GOLD . $is->getRandomLabel($r));

            // Send island chest popup
            if ($is->getChest($sb) !== null) $s->getPlayer()->sendPopup(TF::YELLOW . 'Island chest');
        }
    }

}