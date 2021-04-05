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
    item\Item,
    nbt\tag\ByteTag,
    utils\TextFormat as TF};

use muqsit\invmenu\{
    InvMenu,
    transaction\InvMenuTransaction,
    transaction\InvMenuTransactionResult
};

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
        $this->menu = InvMenu::create(InvMenu::TYPE_HOPPER);
        $this->getMenu()->setName(TF::BOLD . TF::DARK_BLUE . 'Please submit a block');
        $this->getMenu()->setListener(function (InvMenuTransaction $transaction) : InvMenuTransactionResult {
            $out = $transaction->getOut();
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

}