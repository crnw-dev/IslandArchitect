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

namespace Clouria\IslandArchitect\sessions;


use pocketmine\item\Item;
use muqsit\invmenu\InvMenu;
use room17\SkyBlock\SkyBlock;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\CompoundTag;
use room17\SkyBlock\SkyBlockSettings;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use Clouria\IslandArchitect\generator\properties\IslandChest;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use Clouria\IslandArchitect\events\TemplateIslandChestContentsUpdateEvent;
use function is_string;

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
     * @var IslandChest
     */
    private $chest;

    /**
     * @return IslandChest
     */
    public function getChest() : IslandChest {
        return $this->chest;
    }

    /**
     * IslandChestSession constructor.
     * @param PlayerSession $session
     * @param IslandChest $chest
     * @param \Closure|null $callback
     */
    public function __construct(PlayerSession $session, IslandChest $chest, ?\Closure $callback = null) {
        if ($session->getIsland() === null) throw new \RuntimeException('Target session hasn\'t check out an island');
        $this->session = $session;
        $this->callback = $callback;
        $this->chest = $chest;

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
        $contents = $this->getChest()->getContents();
        if (empty($contents)) {
            $reflect = new \ReflectionProperty(SkyBlockSettings::class, 'defaultChestContent');
            $reflect->setAccessible(true);
            foreach ($reflect->getValue(SkyBlock::getInstance()->getSettings()) as $slot => $item) $this->getMenu()->getInventory()->setItem($slot, $item, false);
            $this->changed = true;
        } else foreach ($contents as $slot => $content) {
            if (!isset($content)) continue;
            $content = explode(':', $content);
            if ((int)$content[0] === 0) $item = Item::get((int)$content[1], (int)$content[2], (int)$content[3], isset($content[4]) ? base64_decode((string)$content[4]) : '');
            else {
                $r = $this->getSession()->getIsland()->getRandomById((int)$content[1]);
                if ($r === null) continue;
                $item = $r->getRandomGenerationItem($this->getSession()->getIsland()->getRandomSymbolicItem((int)$content[1]), (int)$content[1]);
            }
            $this->getMenu()->getInventory()->setItem($slot, $item, false);
        }
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
        $ev = new TemplateIslandChestContentsUpdateEvent($this->getSession(), $this->getChest(), $this->$this->getMenu()->getInventory()->getContents());
        $ev->call();
        if ($ev->isCancelled()) return;
        if (is_string($ev->getContents()[0] ?? null)) $this->getChest()->setContents($ev->getContents());
        else foreach ($ev->getContents() as $slot => $item) {
            if (
                !($nbt = $item->getNamedTagEntry('IslandArchitect')) instanceof CompoundTag or
                !($nbt = $nbt->getTag('random-generation', CompoundTag::class)) instanceof CompoundTag or
                !($regex = $nbt->getTag('regex', ListTag::class)) instanceof ListTag
            ) $this->getChest()->setItem($slot, $item->getId(), $item->getDamage(), $item->getCount(), $item->getNamedTag());
            else {
                $regex = RandomGeneration::fromNBT($regex);
                if (!($regexid = $nbt->getTag('regexid', IntTag::class)) instanceof IntTag) {
                    foreach ($this->getSession()->getIsland()->getRandoms() as $i => $sr) if ($sr->equals($regex)) $regexid = $i;
                    if ($regexid === null) $regexid = $this->getSession()->getIsland()->addRandom($regex);
                }
                if (
                    $regexid instanceof IntTag and
                    (($r = $this->getSession()->getIsland()->getRandomById($regexid = $regexid->getValue())) === null or
                        !$r->equals($regex))
                ) $regexid = $this->getSession()->getIsland()->addRandom($regex);
                $this->getChest()->setRandom($slot, $regexid);
            }
        }
        $this->getCallback()();
    }
}