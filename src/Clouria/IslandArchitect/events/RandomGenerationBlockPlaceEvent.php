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

use pocketmine\{
	event\Cancellable,
	level\Position
};

use Clouria\IslandArchitect\{
	runtime\sessions\PlayerSession,
	runtime\TemplateIsland
};

class TemplateIslandCheckOutEvent extends IslandArchitectEvent implements Cancellable {

	public function __construct(PlayerSession $session, int $regexid, Position $pos) {
		$this->regexid = $regexid;
		$this->session = $session;
		$this->pos = $pos;
	}

	/**
	 * @var int
	 */
	protected $regexid;

	public function getRegexId() : int {
		return $this->regexid;
	}

	/**
	 * @var Position
	 */
	protected $pos;

	public function getPosition() : Position {
		return $this->pos;
	}

	/**
	 * @var PlayerSession
	 */
	protected $session;

	public function getSession() : PlayerSession {
		return $this->session;
	}

}