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

use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use pocketmine\{
    item\Item,
    event\Event,
    math\Vector3,
    event\Cancellable
};

class RandomGenerationBlockUpdateEvent extends IslandArchitectEvent implements Cancellable {

    public const PLACE = 0;
    public const PAINT = 1;
    public const BREAK = 2;
    public const UNDO = 3;

    /**
     * @var int
     */
    protected $type;
    /**
     * @var Item|null
     */
    protected $item;
    /**
     * @var RandomGeneration
     */
    protected $regex;
    /**
     * @var Vector3|Vector3[]
     */
    protected $position;
    /**
     * @var PlayerSession
     */
    protected $session;
    /**
     * @var Event
     */
    protected $related_event;

    /**
     * RandomGenerationBlockPlaceEvent constructor.
     * @param PlayerSession $session
     * @param RandomGeneration $regex
     * @param Vector3|Vector3[] $pos
     * @param Item|null $item
     * @param Event|null $related_event
     * @param int $type
     */
    public function __construct(PlayerSession $session, RandomGeneration $regex, $pos, ?Item $item = null, ?Event $related_event = null, int $type = self::PLACE) {
        parent::__construct();
        $this->regex = $regex;
        $this->session = $session;
        $this->position = $pos;
        $this->item = $item;
        $this->type = $type;
        $this->related_event = $related_event;
    }

    /**
     * @return int
     */
    public function getType() : int {
        return $this->type;
    }

    public function getItem() : ?Item {
        return $this->item;
    }

    public function getRandom() : RandomGeneration {
        return $this->getRegex();
    }

    public function getRegex() : RandomGeneration {
        return $this->regex;
    }

    /**
     * @return Vector3|Vector3[]
     */
    public function getPosition() {
        return $this->position;
    }

    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return Event
     */
    public function getRelatedEvent() : ?Event {
        return $this->related_event;
    }

}