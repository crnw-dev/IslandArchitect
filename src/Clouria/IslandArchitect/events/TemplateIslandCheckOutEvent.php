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
use Clouria\IslandArchitect\generator\TemplateIsland;

class TemplateIslandCheckOutEvent extends IslandArchitectEvent {

    /**
     * @var PlayerSession
     */
    protected $session;
    /**
     * @var TemplateIsland
     */
    protected $island;

    public function __construct(PlayerSession $session, TemplateIsland $island) {
        parent::__construct();
        $this->session = $session;
        $this->island = $island;
    }

    public function getSession() : PlayerSession {
        return $this->session;
    }

    public function getIsland() : TemplateIsland {
        return $this->island;
    }

}