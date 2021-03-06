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

use pocketmine\{event\Cancellable, item\Item, level\Position};

use Clouria\IslandArchitect\{
	runtime\sessions\PlayerSession,
	runtime\RandomGeneration
};

class RandomGenerationBlockPlaceEvent extends IslandArchitectEvent implements Cancellable {

	public function __construct(PlayerSession $session, RandomGeneration $regex, Position $pos, Item $item) {
	    parent::__construct();
		$this->regex = $regex;
		$this->session = $session;
		$this->pos = $pos;
		$this->item = $item;
	}

	/**
	 * @var Item
	 */
	protected $item;

	public function getItem() : Item {
		return $this->item;
	}

	/**
	 * @var RandomGeneration
	 */
	protected $regex;

	public function getRandom() : RandomGeneration {
		return $this->regex;
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