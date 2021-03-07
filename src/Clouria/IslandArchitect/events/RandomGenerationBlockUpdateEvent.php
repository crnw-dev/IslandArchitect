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

namespace Clouria\IslandArchitect\events;


use pocketmine\math\Vector3;

class RandomGenerationBlockUpdateEvent extends IslandArchitectEvent {
    /**
     * @var Vector3
     */
    protected $pos;

    /**
     * @var int|null
     */
    protected $regexid;

    /**
     * @var RandomGenerationBlockPlaceEvent|null
     */
    protected $event;

    /**
     * RandomGenerationBlockUpdateEvent constructor.
     * @param Vector3 $pos
     * @param int|null $regexid
     * @param RandomGenerationBlockPlaceEvent|null $event
     */
    public function __construct(Vector3 $pos, ?int $regexid, ?RandomGenerationBlockPlaceEvent $event) {
        $this->pos = $pos;
        $this->regexid = $regexid;
        $this->event = $event;
    }

    public function getPosition() : Vector3 {
        return $this->pos;
    }

    public function getRegexId() : ?int {
        return $this->regexid;
    }

    public function setRegexId(?int $regexid) : void {
        $this->regexid = $regexid;
    }

    public function getPreviousRandomGenerationBlockPlaceEvent() : ?RandomGenerationBlockPlaceEvent {
        return $this->event;
    }
}