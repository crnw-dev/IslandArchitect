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
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\IslandArchitect;
use muqsit\invmenu\transaction\InvMenuTransaction;
use pocketmine\level\particle\FloatingTextParticle;
use Clouria\IslandArchitect\generator\TemplateIsland;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use Clouria\IslandArchitect\events\TemplateIslandExportEvent;
use Clouria\IslandArchitect\generator\tasks\IslandDataEmitTask;
use Clouria\IslandArchitect\events\TemplateIslandCheckOutEvent;
use Clouria\IslandArchitect\generator\tasks\IslandDataLoadTask;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use function max;
use function min;
use function count;
use function round;
use function unlink;
use function explode;
use function get_class;
use function microtime;
use function array_keys;
use function is_callable;
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

    public function checkOutIsland(?TemplateIsland $island = null) : void {
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

    public function listRandoms(?\Closure $callback = null) : void {
        if ($this->getIsland() === null) return;
        if (class_exists(SimpleForm::class)) {
            $f = new SimpleForm(function(Player $p, int $d = null) use ($callback) : void {
                if ($d === null) {
                    if (is_callable($callback)) $callback();
                    return;
                }
                if ($d <= count($this->getIsland()->getRandoms()) or count($this->getIsland()->getRandoms()) < 0x7fffffff) {
                    if ($this->getIsland()->getRandomById($d) === null) $this->editRandom();
                    else $this->editRandom($d);
                }
            });
            foreach ($this->getIsland()->getRandoms() as $i => $r) $f->addButton(TF::BOLD . TF::DARK_BLUE . $this->getIsland()
                                                                                                                 ->getRandomLabel($i) . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(' . count($r->getAllElements()) . ' elements)');
            $f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Regex List');
            $f->addButton(count($this->getIsland()->getRandoms()) < 0x7fffffff ? TF::BOLD . TF::DARK_GREEN . 'New Regex' : TF::BOLD . TF::DARK_GRAY . 'Max limit reached' . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(2147483647 regex)');
            $this->getPlayer()->sendForm($f);
        } else {
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Cannot edit random generation regex due to required virion dependency "libFormAPI"' . TF::ITALIC . TF::GRAY . '(https://github.com/Infernus101/FormAPI) ' . TF::RESET .
                TF::BOLD . TF::RED . 'is not installed. ' . TF::YELLOW . 'An empty regex has been added to the template island data, please edit it manually with an text editor!');
            $this->getIsland()->addRandom(new RandomGeneration);
        }
    }

    public function editRandom(?int $regexid = null) : bool {
        if ($regexid === null and count($this->getIsland()->getRandoms()) < 0x7fffffff) $regexid = $this->getIsland()->addRandom(new RandomGeneration);
        // 2147483647, max limit of int tag value and random generation regex number
        elseif ($regexid === null) return false;
        $form = new SimpleForm(function(Player $p, int $d = null) use ($regexid) : void {
            if ($d === null) {
                $this->listRandoms();
                return;
            }
            $r = $this->getIsland()->getRandomById($regexid);
            if ($r === null) {
                $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'The target random generation regex has been removed!');
                return;
            }
            switch ($d) {
                case 0:
                    $this->listRandomElements($r);
                    break;

                case 1:
                    $this->editRandomLabel($regexid);
                    break;

                case 2:
                    $this->editRandomSymbolic($regexid);
                    break;

                case 3:
                    $this->getPlayer()->getInventory()->addItem($r->getRandomGenerationItem($this->getIsland()->getRandomSymbolicItem($regexid)));
                    $this->getPlayer()->sendMessage(TF::BOLD . TF::YELLOW . 'The random generation block item has been added to your inventory');
                    break;

                case 4:
                    if (!class_exists(InvMenu::class)) {
                        $form = new ModalForm(function(Player $p, bool $d) use ($regexid) : void {
                            $this->editRandom($regexid);
                        });
                        $form->setTitle(TF::BOLD . TF::RED . 'Error');
                        $form->setContent(TF::BOLD . TF::RED . 'Cannot preview generation due to required virion "InvMenu v4"' . TF::ITALIC . TF::GRAY . '(https://github.com/Muqsit/InvMenu) ' . TF::RESET . TF::BOLD . TF::RED . 'is not installed!');
                        $this->getPlayer()->sendForm($form);
                        break;
                    }
                    new GenerationPreviewSession($this, $r, function() use ($regexid) : void {
                        $this->editRandom($regexid);
                    });
                    break;

                case 5:
                    $form = new ModalForm(function(Player $p, bool $d) use ($regexid) : void {
                        if ($d) {
                            $this->getIsland()->removeRandomById($regexid);
                            $this->listRandoms();
                        } else $this->editRandom($regexid);
                    });
                    $form->setTitle(TF::BOLD . TF::DARK_RED . 'Delete Confirmation');
                    $form->setContent(TF::YELLOW . 'Are you sure to ' . TF::BOLD . TF::RED . 'remove' . TF::RESET . TF::YELLOW . ' random generation regex ' . TF::GOLD . '#' . $regexid .
                        (($label = $this->getIsland()->getRandomLabel($regexid, true)) === null ? '' : TF::ITALIC . ' (' . $label . ')') . TF::RESET . TF::YELLOW .
                        ' from your current checked out island ' . TF::BOLD . TF::GOLD . '"' . $this->getIsland()->getName() . '"' . TF::RESET . TF::YELLOW . ' and all the random generation blocks around the world? ' . TF::BOLD . TF::RED
                        . 'This action cannot be undo!'
                    );
                    $form->setButton1('gui.yes');
                    $form->setButton2('gui.no');
                    $this->getPlayer()->sendForm($form);
                    break;
            }
        });
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Modify Regex #' . $regexid);
        $form->addButton(TF::DARK_BLUE . 'Modify content');
        $form->addButton(TF::DARK_BLUE . 'Update label');
        $form->addButton(TF::DARK_BLUE . 'Change symbolic');
        $form->addButton(TF::BLUE . 'Claim random' . "\n" . 'generation block');
        $form->addButton((class_exists(InvMenu::class) ? TF::BLUE : TF::ITALIC . TF::GRAY) . 'Preview generation');
        $form->addButton(TF::BOLD . TF::DARK_RED . 'Remove regex');
        $this->getPlayer()->sendForm($form);
        return true;
    }

    public function listRandomElements(RandomGeneration $regex) : void {
        $elements = $regex->getAllElements();
        $form = new SimpleForm(function(Player $p, int $d = null) use ($elements, $regex) : void {
            if ($d === null) {
                $rid = $this->getIsland()->getRegexId($regex);
                if ($rid !== null) $this->editRandom($rid);
                return;
            }
            $elements = array_keys($elements);
            $element = $elements[$d] ?? null;
            if (!isset($element)) {
                $this->submitBlockSession(function(Item $item) use ($regex) : void {
                    if ($item->getId() === Item::AIR) {
                        if ($regex->getElementChance(Item::AIR) < 1) {
                            $regex->setElementChance(Item::AIR, 0, 1);
                            $this->editRandomElement($regex, Item::AIR);
                        } else $this->listRandomElements($regex);
                        return;
                    }
                    if ($item->getBlock()->getId() === Item::AIR) {
                        $this->errorInvalidBlock(function(Player $p, bool $d) use ($regex) : void {
                            $this->listRandomElements($regex);
                        });
                        return;
                    }
                    $regex->setElementChance($item->getId(), $item->getDamage(), $item->getCount());
                    $this->editRandomElement($regex, $item->getId(), $item->getDamage());
                });
                return;
            }
            $element = explode(':', $element);
            $this->editRandomElement($regex, (int)$element[0], (int)$element[1]);
        });
        $rid = $this->getIsland()->getRegexId($regex);
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Elements of Regex' . (isset($rid) ? ' #' . $rid : ''));
        $totalchance = $regex->getTotalChance();
        foreach ($elements as $element => $chance) {
            $element = explode(':', $element);
            $item = Item::get((int)$element[0], (int)$element[1]);
            $form->addButton(TF::DARK_BLUE . $item->getVanillaName() . ' (' . $element[0] . ':' . $element[1] . ') ' . "\n" . TF::BLUE . ' Chance: ' . $chance . ' (' .

                $chance . ' / ' . ($totalchanceNonZero = $totalchance == 0 ? $chance : $totalchance) . ', ' . round($chance / $totalchanceNonZero * 100, 2) . '%%)');
        }
        $form->addButton(TF::BOLD . TF::DARK_GREEN . 'Add Element');
        $this->getPlayer()->sendForm($form);
    }

    public function editRandomElement(RandomGeneration $regex, int $id, int $meta = 0) : void {
        $form = new CustomForm(function(Player $p, array $d = null) use ($id, $meta, $regex) : void {
            if ($d === null) {
                $this->listRandomElements($regex);
                return;
            }
            $regex->setElementChance($id, $meta, 0); // Reset element chance or element will be duplicated if the ID or meta has changed from form
            $id = (int)$d[0];
            $meta = (int)$d[1];
            $chance = (int)$d[class_exists(InvMenu::class) ? 3 : 2];
            $regex->setElementChance($id, $meta, $chance < 1 ? 0 : $chance);
            if (class_exists(InvMenu::class) and $d[2]) {
                $this->submitBlockSession(function(Item $item) use ($regex, $id, $meta) : void {
                    if ($item->getId() !== Item::AIR) {
                        if ($item->getBlock()->getId() === Item::AIR) {
                            $this->errorInvalidBlock(function(Player $p, bool $d) use ($regex, $id, $meta) : void {
                                $this->editRandomElement($regex, $id, $meta);
                            });
                            return;
                        }
                        $regex->setElementChance($id, $meta, 0);
                        $id = $item->getId();
                        $meta = $item->getDamage();
                        $chance = $item->getCount();
                        $regex->setElementChance($id, $meta, $chance);
                    }
                    $this->editRandomElement($regex, $id, $meta);
                }, Item::get($id, $meta, min(max(1, $chance), 64)));
            } else $this->editRandomElement($regex, $id, $meta);
        });
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Edit Element');
        $form->addInput(TF::AQUA . 'ID', (string)$id, (string)$id);
        $form->addInput(TF::AQUA . 'Meta', (string)$meta, (string)$meta);
        if (class_exists(InvMenu::class)) $form->addToggle(TF::GREEN . 'Open submit block panel');
        $chance = $regex->getElementChance($id, $meta);
        $form->addInput(TF::BOLD . TF::GOLD . 'Chance' . ($chance > 0 ? TF::YELLOW . TF::ITALIC . ' (' .

                $chance . ' / ' . ($totalchanceNonZero = ($totalchance = $regex->getTotalChance()) == 0 ? $chance : $totalchance) . ', ' . round($chance / $totalchanceNonZero * 100, 2) . '%%)' :
                TF::RED . TF::ITALIC . ' (ELEMENT REMOVED, make the chance higher than 0 to keep this element)'
            ),

            (string)$chance, (string)$chance);
        $form->addLabel(TF::ITALIC . TF::AQUA . '(Set chance 0 or lower to remove this element)');
        $this->getPlayer()->sendForm($form);
    }

    protected function errorInvalidBlock(?\Closure $callback = null) : void {
        $form = new ModalForm($callback ?? null);
        $form->setTitle(TF::BOLD . TF::DARK_RED . 'Error');
        $form->setContent(TF::GOLD . 'Submitted item must be a valid block item!');
        $this->getPlayer()->sendForm($form);
    }

    public function editRandomLabel(int $regexid) : void {
        $form = new CustomForm(function(Player $p, array $d = null) use ($regexid) : void {
            if ($d === null) {
                $this->editRandom($regexid);
                return;
            }
            $this->getIsland()->setRandomLabel($regexid, (string)$d[0]);
        });
        $label = (string)$this->getIsland()->getRandomLabel($regexid);
        $form->addInput(TF::BOLD . TF::GOLD . 'Label', $label, $label);
        $this->getPlayer()->sendForm($form);
    }

    public function editRandomSymbolic(int $regexid) : void {
        $this->submitBlockSession(function(Item $item) use ($regexid) : void {
            if ($item->getId() === Item::AIR) {
                $this->editRandom($regexid);
                return;
            }
            if ($item->getBlock()->getId() === Item::AIR) {
                $form = new ModalForm(function(Player $p, bool $d) use ($regexid) : void {
                    $this->editRandom($regexid);
                });
                $form->setTitle(TF::BOLD . TF::DARK_RED . 'Error');
                $form->setContent(TF::GOLD . 'Submitted item must be a valid block item!');
                $this->getPlayer()->sendForm($form);
            }
            $this->getIsland()->setRandomSymbolic($regexid, $item->getId(), $item->getDamage());
        });
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

    public function overviewIsland(string $name = '', bool $noperm = false, bool $invalidname = false, bool $nochange = false) : bool {
        if (class_exists(SimpleForm::class)) return false;
        if ($this->getIsland() === null) {
            $form = new CustomForm(function(Player $p, array $d = null) use ($noperm, $invalidname, $name) : void {
                if ($d === null) return;
                if (empty($d[1])) $isname = $name;
                else $isname = $d[1];
                if (!preg_match('/[0-9a-z-_]+/i', '', $d[1])) {
                    $this->overviewIsland($isname, false, true);
                    return;
                }
                if (!$this->getPlayer()->hasPermission('island-architect.convert') and !$this->getPlayer()->hasPermission('island-architect.convert.' . $isname)) {
                    $this->overviewIsland($d[1], true, false);
                    return;
                }
                $time = microtime(true);
                $this->getPlayer()->sendMessage(TF::YELLOW . 'Loading island ' . TF::GOLD . '"' . $isname . '"...');
                $callback = function(?TemplateIsland $is, string $filepath) use ($time, $isname) : void {
                    if (!$this->getPlayer()->isOnline()) return;
                    if (!isset($is)) {
                        $is = new TemplateIsland($isname);
                        IslandArchitect::getInstance()->addDefaultRandomRegex($is);
                        $is->noMoreChanges();
                        $this->getPlayer()->sendMessage(TF::BOLD . TF::GOLD . 'Created' . TF::GREEN . ' new island "' . $is->getName() . '"!');
                    } else $this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Checked out island "' . $is->getName() . '"! ' . TF::ITALIC . TF::GRAY . '(' . round(microtime(true) - $time, 2) . 's)');
                    $this->checkOutIsland($is);
                    $ev = new TemplateIslandCheckOutEvent($this, $is);
                    $ev->call();
                    $this->overviewIsland();
                };
                foreach (IslandArchitect::getInstance()->getSessions() as $s) if (
                    ($i = $s->getIsland()) !== null and
                    $i->getName() === $isname
                ) {
                    $callback($i, IslandArchitect::getInstance()->getDataFolder() . $i->getName());
                    return;
                }
                $task = new IslandDataLoadTask($isname, $callback);
                Server::getInstance()->getAsyncPool()->submitTask($task);
            });
            $content = '';
            if ($noperm) $content .= TF::BOLD . TF::RED . 'You don\' have permission to access this island!';
            if ($invalidname) $content .= TF::BOLD . TF::RED . 'Invalid island name pattern! (0-9 / a-z / -_)';
            $form->addLabel($content);
            $form->addInput(TF::BOLD . TF::GOLD . 'Island name:', empty($name) ? 'Island name' : $name, $name);
            $this->getPlayer()->sendForm($form);
        }
        $form = new SimpleForm(function(Player $p, int $d = null) : void {
            if ($d === null) return;
            switch ($d) {

                case 0:
                    $this->updateIslandSettings();
                    break;

                case 1:
                    $this->listRandoms(function() : void {
                        $this->overviewIsland();
                    });
                    break;

                case 2:
                    $this->changeIslandLevel();
                    break;

                case 3:
                    if (!$this->getIsland()->hasChanges()) $this->overviewIsland('', false, false, true);
                    else {
                        $this->saveIsland();
                        $this->overviewIsland();
                    }
                    break;

                case 4:
                    $this->saveIsland();
                    $this->checkOutIsland();
                    $this->overviewIsland();
                    break;

                case 5:
                    $form = new ModalForm(function(Player $p, bool $d) : void {
                        if (!$d) {
                            $this->overviewIsland();
                            return;
                        }
                        $this->saveIsland();
                        $name = $this->getIsland()->getName();
                        $this->checkOutIsland();
                        @unlink(IslandArchitect::getInstance()->getDataFolder() . 'islands/' . $name . '.json');
                        $this->overviewIsland();
                    });
                    $form->setTitle(TF::BOLD . TF::RED . 'Island Deletion');
                    $form->setContent(TF::YELLOW . 'Are you sure to ' . TF::BOLD . TF::RED . 'delete' . TF::RESET . TF::YELLOW . ' template island ' . TF::BOLD . TF::GOLD . '"' . $this->getIsland()->getName() . '"' . TF::RESET .
                        TF::YELLOW . ', the file will be directly ' . TF::BOLD . TF::RED . 'unlink' . TF::RESET . TF::YELLOW . ' from the file system and this action cannot be undone!');
                    $form->setButton1('gui.yes');
                    $form->setButton1('gui.no');
                    $this->getPlayer()->sendForm($form);
                    break;
            }
        });
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Update island settings');
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Modify random' . "\n" . 'generation regex');
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Change island level');
        $form->addButton(!$nochange ?
            TF::BOLD . ($this->getIsland()->hasChanges() ? TF::DARK_GREEN : TF::GRAY) . 'Save island' :
            TF::BOLD . TF::ITALIC . TF::GRAY . 'No changes' . "\n" . 'to save');
        $form->addButton(TF::BOLD . TF::DARK_RED . 'Exit island' . "\n" . TF::RESET . TF::ITALIC . TF::RED . '(Auto save)');
        $form->addButton(TF::BOLD . TF::DARK_RED . 'Delete island');
        return true;
    }

    public function changeIslandLevel(?string $level = null) : void {
        if ($level === null) {
            return;
        }
        $is = $this->getIsland();
        $form = new ModalForm(function(Player $p, bool $d) use ($is, $level) : void {
            if (!$d) return;
            $is->setLevel($level);
            $is->setStartCoord(null);
            $is->setEndCoord(null);
            $is->setSpawn(null);
            $is->setYOffset(null);
            $this->getPlayer()->sendMessage(TF::YELLOW . 'Island level set to ' . TF::GOLD . '"' . $level . '"');
        });
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Change Confirmation');
        $form->setContent(TF::YELLOW . 'All the other settings of the template island will be ' . TF::BOLD . TF::RED . 'reset' . TF::RESET . TF::YELLOW . ' after changing island level, are you sure to proceed?');
        $form->setButton1('gui.yes');
        $form->setButton2('gui.no');
        $this->getPlayer()->sendForm($form);
    }

    public function updateIslandSettings() : void {
        $form = new CustomForm(function(Player $p, array $d = null) : void {
            if ($d === null) {
                $this->overviewIsland();
                return;
            }

            if ((int)$d[0] === 0) $vec = new Vector3((int)$d[1], (int)$d[2], (int)$d[3]);
            elseif ($this->getPlayer()->getLevel()->getFolderName() === $this->getIsland()->getLevel()) $vec = $this->getPlayer()->asVector3();
            else return; // TODO: Error
            if (!$vec->equals($this->getIsland()->getStartCoord())) $this->getIsland()->setStartCoord($vec);

            if ((int)$d[4] === 0) $vec = new Vector3((int)$d[5], (int)$d[6], (int)$d[7]);
            elseif ($this->getPlayer()->getLevel()->getFolderName() === $this->getIsland()->getLevel()) $vec = $this->getPlayer()->asVector3();
            else return;
            if (!$vec->equals($this->getIsland()->getEndCoord())) $this->getIsland()->setEndCoord($vec);

            // TODO: Y offset validation
            if ($this->getIsland()->getYOffset() !== (int)$d[8]) $this->getIsland()->setYOffset((int)$d[8]);
        });
        $form->addDropdown(TF::BOLD . TF::GOLD . 'Start coordinate: ', [
            TF::BLUE . 'Update coordinate to: ',
            ($this->getPlayer()->getLevel()->getFolderName() === $this->getIsland()->getLevel() ? TF::BLUE : TF::GRAY) . 'Update coordinate to player position'
        ]);
        $vec = $this->getIsland()->getStartCoord() ?? new Vector3(0, 0, 0);
        $form->addInput(TF::AQUA . 'X: ', (string)$vec->getFloorX(), (string)$vec->getFloorX());
        $form->addInput(TF::AQUA . 'Y: ', (string)$vec->getFloorY(), (string)$vec->getFloorY());
        $form->addInput(TF::AQUA . 'Z: ', (string)$vec->getFloorZ(), (string)$vec->getFloorZ());

        $form->addDropdown(TF::BOLD . TF::GOLD . 'End coordinate: ', [
            TF::BLUE . 'Update coordinate to: ',
            ($this->getPlayer()->getLevel()->getFolderName() === $this->getIsland()->getLevel() ? TF::BLUE : TF::GRAY) . 'Update coordinate to player position'
        ]);
        $vec = $this->getIsland()->getEndCoord() ?? new Vector3(0, 0, 0);
        $form->addInput(TF::AQUA . 'X: ', (string)$vec->getFloorX(), (string)$vec->getFloorX());
        $form->addInput(TF::AQUA . 'Y: ', (string)$vec->getFloorY(), (string)$vec->getFloorY());
        $form->addInput(TF::AQUA . 'Z: ', (string)$vec->getFloorZ(), (string)$vec->getFloorZ());

        $form->addInput(TF::BOLD . TF::GOLD . 'Y offset: ', (string)$this->getIsland()->getYOffset(), (string)$this->getIsland()->getYOffset());

        $this->getPlayer()->sendForm($form);
    }
}