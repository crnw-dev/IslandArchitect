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

namespace Clouria\IslandArchitect\sessions;

use pocketmine\{
    item\Item,
    nbt\tag\ByteTag,
    utils\TextFormat as TF
};
use muqsit\invmenu\{
    InvMenu,
    transaction\InvMenuTransaction,
    transaction\InvMenuTransactionResult
};
use function class_exists;

class SubmitBlockSession {

    /**
     * @var PlayerSession
     */
    private $session;

    /**
     * @var \Closure
     */
    private $callback;

    /**
     * @var InvMenu
     */
    private $menu;

    public const ACTION_FRAME = 0;
    /**
     * @var Item|null
     */
    protected $default = null;

    public function __construct(PlayerSession $session, \Closure $callback, ?Item $default = null) {
        $this->session = $session;
        $this->setCallback($callback);
        $this->default = $default;
        if (!class_exists(InvMenu::class)) {
            $callback($default ?? Item::get(Item::AIR));
            return;
        }
        $this->menu = InvMenu::create(InvMenu::TYPE_HOPPER);
        $this->getMenu()->setName(TF::BOLD . TF::DARK_BLUE . 'Please submit a block');
        $this->getMenu()->setListener(function (InvMenuTransaction $transaction) : InvMenuTransactionResult {
            $out = static::inputConversion($transaction->getOut());
            if (
                ($nbt = $out->getNamedTagEntry('action')) instanceof ByteTag and
                $nbt->getValue() === self::ACTION_FRAME
            ) return $transaction->discard();
            return $transaction->continue();
        });
        $this->getMenu()->setInventoryCloseListener(function () : void {
            $item = $this->getMenu()->getInventory()->getItem(2);
            $this->getSession()->getPlayer()->getInventory()->addItem($item);
            $this->getCallback()($item);
        });
        $this->panelInit();

        $this->getMenu()->send($session->getPlayer());
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return \Closure
     */
    public function getCallback() : \Closure {
        return $this->callback;
    }

    /**
     * @param \Closure $callback Compatible with <code>function(<@link Item> $item) {}</code>
     */
    public function setCallback(\Closure $callback) : void {
        $this->callback = $callback;
    }

    /**
     * @return InvMenu
     */
    public function getMenu() : InvMenu {
        return $this->menu;
    }

    protected function panelInit() : void {
        $inv = $this->getMenu()->getInventory();
        $item = Item::get(Item::INVISIBLEBEDROCK);
        $item->setCustomName(TF::RESET);
        $item->setNamedTagEntry(new ByteTag('action', self::ACTION_FRAME));
        for ($slot = 0; $slot < 5; $slot++) {
            if ($slot !== 2) $inv->setItem($slot, $item, false);
            elseif (isset($this->default)) $inv->setItem($slot, $this->default, false);
        }
    }

    protected static function inputConversion(Item $item, &$succeed = false) : Item {
		$succeed = false;
		$count = $item->getCount();
		$nbt = $item->getNamedTag();
		switch (true) {
			case $item->getId() === Item::BUCKET and $item->getDamage() === 8:
			case $item->getId() === Item::POTION and $item->getDamage() === 0:
				$item = Item::get(Item::WATER);
				$succeed = true;
				break;

			case $item->getId() === Item::BUCKET and $item->getDamage() === 10:
				$item = Item::get(Item::LAVA);
				$succeed = true;
				break;

			case $item->getId() === Item::BUCKET and $item->getDamage() === 0:
			case $item->getId() === Item::GLASS_BOTTLE and $item->getDamage() === 0:
			case $item->getId() === Item::BOWL and $item->getDamage() === 0:
				$item = Item::get(Item::AIR);
				$succeed = true;
				break;
		}
		$item->setCount($count);
		foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
		return $item;
	}

}