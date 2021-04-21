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

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use muqsit\invmenu\InvMenu;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use jojoe77777\FormAPI\ModalForm;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\IslandArchitect;
use muqsit\invmenu\transaction\InvMenuTransaction;
use pocketmine\level\particle\FloatingTextParticle;
use Clouria\IslandArchitect\generator\TemplateIsland;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use Clouria\IslandArchitect\events\TemplateIslandExportEvent;
use Clouria\IslandArchitect\generator\tasks\IslandDataEmitTask;
use function max;
use function min;
use function round;
use function get_class;
use function microtime;
use function class_exists;
use function spl_object_id;

class PlayerSession {

    /**
     * @var array<mixed, FloatingTextParticle>
     */
    protected $floatingtext = [];
    /**
     * @var scalar[]
     */
    protected $viewingft = [];
    /**
     * @var TemplateIsland|null
     */
    protected $island = null;
    /**
     * @var bool
     */
    protected $save_lock = false;
    /**
     * @var Player
     */
    private $player;
    /**
     * @var bool
     */
    private $export_lock = false;

    public function __construct(Player $player) {
        $this->player = $player;
    }

    /**
     * @param Player $player
     * @param PlayerSession|null $session
     * @return bool true = No island checked out
     */
    public static function errorCheckOutRequired(Player $player, ?PlayerSession $session) : bool {
        if ($session !== null and $session->getIsland() !== null) return false;
        $player->sendMessage(TF::BOLD . TF::RED . 'Please check out an island first!' . TF::GRAY . TF::ITALIC . ' ("/ia island <Island data file name: string>")');
        return true;
    }

    public function checkOutIsland(TemplateIsland $island) : void {
        if ($this->export_lock) {
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'An island is exporting in background, please wait until the island export is finished!');
            return;
        }
        $this->island = $island;
    }

    public function getPlayer() : Player {
        return $this->player;
    }

    /**
     * @param scalar $id
     * @param bool $nonnull
     * @return FloatingTextParticle|null
     */
    public function getFloatingText($id, bool $nonnull = false) : ?FloatingTextParticle {
        if (isset($this->floatingtext[$id])) return $this->floatingtext[$id];
        if ($nonnull) return ($this->floatingtext[$id] = new FloatingTextParticle(new Vector3(0, 0, 0), ''));
        return null;
    }

    public function close() : void {
        $this->saveIsland();
        if (!$this->getPlayer()->isOnline()) return;
        foreach ($this->floatingtext as $ft) {
            $ft->setInvisible();
            $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
        }
    }

    public function saveIsland() : void {
        if ($this->save_lock) return;
        if (($island = $this->getIsland()) === null) return;
        if (!$island->hasChanges()) return;
        $this->save_lock = true;
        $time = microtime(true);
        IslandArchitect::getInstance()->getLogger()->debug('Saving island "' . $island->getName() . '" (' . spl_object_id($island) . ')');
        $task = new IslandDataEmitTask($island, [], function(string $file) use ($island, $time) : void {
            $this->save_lock = false;
            IslandArchitect::getInstance()->getLogger()->debug('Island "' . $island->getName() . '" (' . spl_object_id($island) . ') save completed (' . round(microtime(true) - $time, 2) . 's)');
            $island->noMoreChanges();
        });
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    public function getIsland() : ?TemplateIsland {
        return $this->island;
    }

    public function exportIsland() : void {
        if (($island = $this->getIsland()) === null) return;
        if (!$island->readyToExport()) {
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Please set the island start and end coordinate first!');
            return;
        }
        $this->export_lock = true;
        $this->island = null;
        $time = microtime(true);
        $this->getPlayer()->sendMessage(TF::YELLOW . 'Queued export task for island "' . $island->getName() . '"...');

        $sc = $island->getStartCoord();
        $ec = $island->getEndCoord();

        for ($x = min($sc->getFloorX(), $ec->getFloorX()) >> 4; $x <= (max($sc->getFloorX(), $ec->getFloorX()) >> 4); $x++) for ($z = min($sc->getFloorZ(), $ec->getFloorZ()) >> 4; $z <= (max($sc->getFloorZ(), $ec->getFloorZ()) >> 4); $z++) {
            while (($level = Server::getInstance()->getLevelByName($island->getLevel())) === null) {
                if ($wlock ?? false) {
                    $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Island world (' . $island->getLevel() . ') is missing!');
                    $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Export task aborted.');
                    $this->export_lock = false;
                    return;
                }
                Server::getInstance()->loadLevel($island->getLevel());
                $wlock = true;
            }
            $chunk = $level->getChunk($x, $z, true);
            if ($chunk === null) $this->getPlayer()->sendMessage(TF::BOLD . TF::YELLOW . 'Warning: ' . TF::RED . 'Failed to load required chunk ' . $x . ', ' . $z);
            else {
                $chunks[0][$hash = Level::chunkHash($x, $z)] = $chunk->fastSerialize();
                $chunks[1][$hash] = get_class($chunk);
            }
        }
        if (!isset($chunks)) {
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Critical: Failed to load required chunks');
            return;
        }
        $this->getPlayer()->sendMessage(TF::GOLD . 'Start exporting...');
        $task = new IslandDataEmitTask($island, $chunks, function(string $file) use ($time, $island) : void {
            $this->export_lock = false;
            $this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Export completed!' . TF::ITALIC . TF::GRAY . ' (' . round(microtime(true) - $time, 2) . 's)');
            $ev = new TemplateIslandExportEvent($this, $island, $file);
            $ev->call();
        }, function() use ($island) : void {
            $this->export_lock = false;
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Critical: Export task crashed' . TF::ITALIC . TF::GRAY . ' (The selected region might be too big or an unexpected error occurred)');
            // Actually there is no need to restore data after a crash since normally it won't affect any original data
        });

        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    /**
     * @param scalar $id
     * @return bool
     */
    public function showFloatingText($id) : bool {
        if (!isset($this->floatingtext[$id])) return false;
        if (in_array($id, $this->viewingft, true)) return false;
        $this->viewingft[] = $id;

        $ft = $this->floatingtext[$id];
        $ft->setInvisible(false);
        $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
        return true;
    }

    /**
     * @param scalar $id
     * @return bool
     */
    public function hideFloatingText($id) : bool {
        if (!isset($this->floatingtext[$id])) return false;
        if (($r = array_search($id, $this->viewingft, true)) === false) return false;
        unset($this->viewingft[$r]);

        $ft = $this->floatingtext[$id];
        $ft->setInvisible(true);
        $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
        return true;
    }

    protected function errorInvalidBlock(?\Closure $callback = null) : void {
        $form = new ModalForm($callback ?? null);
        $form->setTitle(TF::BOLD . TF::DARK_RED . 'Error');
        $form->setContent(TF::GOLD . 'Submitted item must be a valid block item!');
        $this->getPlayer()->sendForm($form);
    }

    public function submitBlockSession(?\Closure $callback = null, ?Item $default = null, bool $convert = true) : bool {
        if (!class_exists(InvMenu::class)) {
            $callback($default ?? Item::get(Item::AIR));
            return false;
        }
        $menu = InvMenu::create(InvMenu::TYPE_HOPPER);
        $menu->setName(TF::BOLD . TF::DARK_BLUE . 'Please submit a block');
        $menu->setListener(function(InvMenuTransaction $transaction) use ($convert) : InvMenuTransactionResult {
            $out = $transaction->getOut();
            if ($convert) $out = static::inputConversion($out);
            if (
                ($nbt = $out->getNamedTagEntry('action')) instanceof ByteTag and
                $nbt->getValue() === 0
            ) return $transaction->discard();
            return $transaction->continue();
        });
        $menu->setInventoryCloseListener(function() use ($callback, $menu) : void {
            $item = $menu->getInventory()->getItem(2);
            $this->getPlayer()->getInventory()->addItem($item);
            $callback($item);
        });

        $inv = $menu->getInventory();
        $item = Item::get(Item::INVISIBLEBEDROCK);
        $item->setCustomName(TF::RESET);
        $item->setNamedTagEntry(new ByteTag('action', 0));
        for ($slot = 0; $slot < 5; $slot++) {
            if ($slot !== 2) $inv->setItem($slot, $item, false);
            elseif (isset($this->default)) $inv->setItem($slot, $this->default, false);
        }

        $menu->send($this->getPlayer());
        return true;
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