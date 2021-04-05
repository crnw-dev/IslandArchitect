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
    item\Item};
use room17\SkyBlock\{
    SkyBlock,
    utils\Utils};
use muqsit\invmenu\{
    InvMenu,
    transaction\InvMenuTransaction,
    transaction\InvMenuTransactionResult};

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
     * @var \Closure|null
     */
    private $callback;
    /**
     * @var bool
     */
    private $changed = false;

    /**
     * IslandChestSession constructor.
     * @param PlayerSession $session
     * @param \Closure|null $callback
     */
    public function __construct(PlayerSession $session, ?\Closure $callback = null) {
        if ($session->getIsland() === null) throw new \RuntimeException('Target session hasn\'t check out an island');
        $this->session = $session;
        $this->callback = $callback;

        if (!isset($this->menu)) $this->menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $this->menu->setListener(function(InvMenuTransaction $transaction) : InvMenuTransactionResult {
            $this->changed = true;
            return $transaction->continue();
        });
        $this->menu->setInventoryCloseListener(\Closure::fromCallable([$this, 'closeCallback']));
        $this->putDefaultItems();
        $this->menu->send($session->getPlayer());
        $this->menu->getInventory()->sendContents($session->getPlayer());
    }

    protected function putDefaultItems() : void {
        foreach (SkyBlock::getInstance()->getSettings()->getChestContentByGenerator($this->getSession()->getIsland()->getName()) as $slot => $item) $this->menu->getInventory()->setItem($slot, $item, false);
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return InvMenu
     */
    public function getMenu() : InvMenu {
        return $this->menu;
    }

    /**
     * @return \Closure|null
     */
    public function getCallback() : ?\Closure {
        return $this->callback;
    }

    /**
     * @return bool
     */
    public function isChanged() : bool {
        return $this->changed;
    }

    protected function closeCallback() : void {
        if (!$this->isChanged()) return;
        $slottrack = 1;
        foreach ($this->menu->getInventory()->getContents(true) as $slot => $item) {
            for ($i = $slottrack; $i < $slot; $i++) $contents[] = (string)Item::AIR;
            $contents[] = $item->getId() . ', ' . $item->getDamage() . ', ' . $item->getCount();
            $slottrack++;
        }
        $reflect = new \ReflectionProperty($class = SkyBlock::getInstance()->getSettings(), 'generatorChestContent');
        $reflect->setAccessible(true);
        $map = $reflect->getValue($class);
        $map[$this->getSession()->getIsland()->getName()] = Utils::parseItems($contents ?? []);
        $reflect->setValue($class, $map);

        $reflect = new \ReflectionProperty($class, 'config');
        $reflect->setAccessible(true);
        $conf = $reflect->getValue($class);
        $confc = $conf->get('CustomChestContent', []);
        $confc[$this->getSession()->getIsland()->getName()] = $contents ?? [];
        $conf->save();
    }
}