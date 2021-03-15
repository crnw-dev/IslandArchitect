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

namespace Clouria\IslandArchitect\runtime\sessions;


use pocketmine\{
    Player,
    item\Item
};

use muqsit\invmenu\InvMenu;
use room17\SkyBlock\SkyBlock;

class IslandChestSession {

    /**
     * @var PlayerSession
     */
    private $session;
    /**
     * @var InvMenu
     */
    private $menu;

    /**
     * IslandChestSession constructor.
     * @param PlayerSession $session
     * @param \Closure|null $callback
     */
    public function __construct(PlayerSession $session, ?\Closure $callback = null) {
        if ($session->getIsland() === null) throw new \RuntimeException('Target session hasn\'t check out an island');
		$this->session = $session;
		$this->callback = $callback;
        if (self::errorInvMenuNotInstalled($session->getPlayer())) return;

		if (!isset($this->menu)) $this->menu = InvMenu::create(InvMenu::TYPE_CHEST);
		$this->menu->setInventoryCloseListener(\Closure::fromCallable([$this, 'closeCallback']));
		$this->putDefaultItems();
		$this->menu->send($session->getPlayer());
		$this->menu->getInventory()->sendContents($session->getPlayer());
    }

    protected function putDefaultItems() : void {
        foreach (SkyBlock::getInstance()->getSettings()->getChestContentByGenerator($this->getSession()->getIsland()->getName()) as $slot => $item) $this->menu->getInventory()->setItem($slot, $item, false);
    }

    protected function closeCallback() : void {
        $slottrack = 1;
        foreach ($this->menu->getInventory()->getContents(true) as $slot => $item) {
            for ($i=$slottrack; $i < $slot; $i++) $contents[] = (string)Item::AIR;
            $contents[] = $item->getId() . ', ' . $item->getDamage() . ', ' . $item->getCount();
            $slottrack++;
        }
        $reflect = new \ReflectionProperty($class = SkyBlock::getInstance()->getSettings(), 'generatorChestContent');
        $reflect->setAccessible(true);
        $map = $reflect->getValue($class);
        $map[$this->getSession()->getIsland()->getName()] = $contents;
        $reflect->setValue($class, $map);

        $reflect = new \ReflectionProperty($class, 'config');
        $reflect->getValue($class)->save();
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    public static function errorInvMenuNotInstalled(Player $player) : bool {
        return InvMenuSession::errorInvMenuNotInstalled($player);
    }
}