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
    item\Item,
    event\Cancellable,
    math\Vector3};

use Clouria\IslandArchitect\{
    runtime\RandomGeneration,
    runtime\sessions\PlayerSession
};

class RandomGenerationBlockPaintEvent extends IslandArchitectEvent implements Cancellable {
    /**
     * @var PlayerSession|null
     */
    protected $session;
    /**
     * @var RandomGeneration
     */
    protected $regex;
    /**
     * @var array
     */
    protected $blocks;
    /**
     * @var Item
     */
    protected $item;

    /**
     * RandomGenerationBlockPaintEvent constructor.
     * @param PlayerSession|null $session
     * @param RandomGeneration $regex
     * @param array $blocks
     * @param Item $item
     */
    public function __construct(?PlayerSession $session, RandomGeneration $regex, array $blocks, Item $item) {
        parent::__construct();
        $this->session = $session;
        $this->regex = $regex;
        $this->blocks = $blocks;
        $this->item = $item;
    }

    /**
     * @return PlayerSession|null
     */
    public function getSession() : ?PlayerSession {
        return $this->session;
    }

    /**
     * @return RandomGeneration
     */
    public function getRegex() : RandomGeneration {
        return $this->regex;
    }

    /**
     * @return Vector3[]
     */
    public function getBlocks() : array {
        return $this->blocks;
    }

    /**
     * @return Item
     */
    public function getItem() : Item {
        return $this->item;
    }
}