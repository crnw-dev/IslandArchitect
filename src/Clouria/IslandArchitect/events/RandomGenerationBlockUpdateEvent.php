<?php
/*

     					_________	  ______________		
     				   /        /_____|_           /
					  /————/   /        |  _______/_____    
						  /   /_     ___| |_____       /
						 /   /__|    ||    ____/______/
						/   /    \   ||   |   |   
					   /__________\  | \   \  |
					       /        /   \   \ |
						  /________/     \___\|______
						                   |         \ 
							  PRODUCTION   \__________\	

							   翡翠出品 。 正宗廢品  
 
*/
declare(strict_types=1);

namespace Clouria\IslandArchitect\events;


use pocketmine\event\Cancellable;
use pocketmine\math\Vector3;

class RandomGenerationBlockUpdateEvent extends IslandArchitectEvent implements Cancellable {
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