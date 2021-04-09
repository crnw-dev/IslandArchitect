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

use pocketmine\level\Position;
use pocketmine\event\Cancellable;
use Clouria\IslandArchitect\runtime\RandomGeneration;
use Clouria\IslandArchitect\runtime\sessions\PlayerSession;

class RandomGenerationBlockBreakEvent extends IslandArchitectEvent implements Cancellable {

    /**
     * @var PlayerSession
     */
    protected $session;
    /**
     * @var RandomGeneration
     */
    protected $regex;
    /**
     * @var Position
     */
    protected $pos;

    public function __construct(PlayerSession $session, int $regex, Position $pos) {
        parent::__construct();
        $this->session = $session;
        $this->regex = $regex;
        $this->pos = $pos;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return RandomGeneration
     */
    public function getRegex() : RandomGeneration {
        return $this->regex;
    }

    /**
     * @return Position
     */
    public function getPos() : Position {
        return $this->pos;
    }

}