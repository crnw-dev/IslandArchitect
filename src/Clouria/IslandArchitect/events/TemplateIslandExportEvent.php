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

use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\generator\TemplateIsland;

class TemplateIslandExportEvent extends IslandArchitectEvent {

    /**
     * @var PlayerSession
     */
    protected $session;
    /**
     * @var TemplateIsland
     */
    protected $island;
    /**
     * @var string
     */
    protected $file;

    public function __construct(PlayerSession $session, TemplateIsland $island, string $file) {
        parent::__construct();
        $this->session = $session;
        $this->island = $island;
        $this->file = $file;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return TemplateIsland
     */
    public function getIsland() : TemplateIsland {
        return $this->island;
    }

    /**
     * @return string
     */
    public function getFile() : string {
        return $this->file;
    }

}