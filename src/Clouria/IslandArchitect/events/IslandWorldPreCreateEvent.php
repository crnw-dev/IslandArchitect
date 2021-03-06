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

use pocketmine\event\Event;
use room17\SkyBlock\session\Session;

class IslandWorldPreCreateEvent extends Event {

    /**
     * @var Session
     */
    private $session;

    /**
     * @var string The island world name
     */
    private $identifier;

    /**
     * @var string
     */
    private $type;

    public function __construct(Session $session, string $identifier, string $type) {
        $this->session = $session;
        $this->identifier = $identifier;
        $this->type = $type;
    }

    /**
     * @return string The island world name
     */
    public function getIdentifier() : string {
        return $this->identifier;
    }

    /**
     * @return Session
     */
    public function getSession() : Session {
        return $this->session;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void {
        $this->identifier = $identifier;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void {
        $this->type = $type;
    }
}