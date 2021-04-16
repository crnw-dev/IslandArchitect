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

namespace Clouria\IslandArchitect\events;

use pocketmine\item\Item;
use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\generator\properties\IslandChest;

class TemplateIslandChestContentsUpdateEvent extends IslandArchitectEvent {

    /**
     * @var PlayerSession
     */
    private $session;
    /**
     * @var IslandChest
     */
    private $chest;
    /**
     * @var array
     */
    private $contents;

    public function __construct(PlayerSession $session, IslandChest $chest, array $contents) {
        parent::__construct();
        $this->session = $session;
        $this->chest = $chest;
        $this->contents = $contents;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return IslandChest
     */
    public function getChest() : IslandChest {
        return $this->chest;
    }

    /**
     * @return Item[]|string[]
     */
    public function getContents() : array {
        return $this->contents;
    }

    /**
     * @param Item[]|string[] $contents
     */
    public function setContents(array $contents) : void {
        $this->contents = $contents;
    }

}