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
use pocketmine\math\Vector3;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\TemplateIsland;
use Clouria\IslandArchitect\events\TemplateIslandCheckOutEvent;
use Clouria\IslandArchitect\generator\tasks\IslandDataLoadTask;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;

class IslandFormSession {

    /**
     * @var PlayerSession
     */
    private $session;

    public function __construct(PlayerSession $session) {
        $this->session = $session;
    }

    public function overviewIsland(string $name = '', bool $noperm = false, bool $invalidname = false, bool $nochange = false) : bool {
        if (class_exists(SimpleForm::class)) return false;
        if ($this->getSession()->getIsland() === null) {
            $form = new CustomForm(function(Player $p, array $d = null) use ($noperm, $invalidname, $name) : void {
                if ($d === null) return;
                if (empty($d[1])) $isname = $name;
                else $isname = $d[1];
                if (!preg_match('/[0-9a-z-_]+/i', '', $d[1])) {
                    $this->overviewIsland($isname, false, true);
                    return;
                }
                if (!$this->getSession()->getPlayer()->hasPermission('island-architect.convert') and !$this->getSession()->getPlayer()->hasPermission('island-architect.convert.' . $isname)) {
                    $this->overviewIsland($d[1], true, false);
                    return;
                }
                $time = microtime(true);
                $this->getSession()->getPlayer()->sendMessage(TF::YELLOW . 'Loading island ' . TF::GOLD . '"' . $isname . '"...');
                $callback = function(?TemplateIsland $is, string $filepath) use ($time, $isname) : void {
                    if (!$this->getSession()->getPlayer()->isOnline()) return;
                    if (!isset($is)) {
                        $is = new TemplateIsland($isname);
                        IslandArchitect::getInstance()->addDefaultRandomRegex($is);
                        $is->noMoreChanges();
                        $this->getSession()->getPlayer()->sendMessage(TF::BOLD . TF::GOLD . 'Created' . TF::GREEN . ' new island "' . $is->getName() . '"!');
                    } else $this->getSession()->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Checked out island "' . $is->getName() . '"! ' . TF::ITALIC . TF::GRAY . '(' . round(microtime(true) - $time, 2) . 's)');
                    $this->getSession()->checkOutIsland($is);
                    $ev = new TemplateIslandCheckOutEvent($this->getSession(), $is);
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
            $this->getSession()->getPlayer()->sendForm($form);
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
                    if (!$this->getSession()->getIsland()->hasChanges()) $this->overviewIsland('', false, false, true);
                    else {
                        $this->getSession()->saveIsland();
                        $this->overviewIsland();
                    }
                    break;

                case 4:
                    $this->getSession()->saveIsland();
                    $this->getSession()->checkOutIsland();
                    $this->overviewIsland();
                    break;

                case 5:
                    $form = new ModalForm(function(Player $p, bool $d) : void {
                        if (!$d) {
                            $this->overviewIsland();
                            return;
                        }
                        $this->getSession()->saveIsland();
                        $name = $this->getSession()->getIsland()->getName();
                        $this->getSession()->checkOutIsland();
                        @unlink(IslandArchitect::getInstance()->getDataFolder() . 'islands/' . $name . '.json');
                        $this->overviewIsland();
                    });
                    $form->setTitle(TF::BOLD . TF::RED . 'Island Deletion');
                    $form->setContent(TF::YELLOW . 'Are you sure to ' . TF::BOLD . TF::RED . 'delete' . TF::RESET . TF::YELLOW . ' template island ' . TF::BOLD . TF::GOLD . '"' . $this->getSession()->getIsland()->getName() . '"' .
                        TF::RESET .
                        TF::YELLOW . ', the file will be directly ' . TF::BOLD . TF::RED . 'unlink' . TF::RESET . TF::YELLOW . ' from the file system and this action cannot be undone!');
                    $form->setButton1('gui.yes');
                    $form->setButton1('gui.no');
                    $this->getSession()->getPlayer()->sendForm($form);
                    break;
            }
        });
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Update island settings');
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Modify random' . "\n" . 'generation regex');
        $form->addButton(TF::BOLD . TF::DARK_BLUE . 'Change island level');
        $form->addButton(!$nochange ?
            TF::BOLD . ($this->getSession()->getIsland()->hasChanges() ? TF::DARK_GREEN : TF::GRAY) . 'Save island' :
            TF::BOLD . TF::ITALIC . TF::GRAY . 'No changes' . "\n" . 'to save');
        $form->addButton(TF::BOLD . TF::DARK_RED . 'Exit island' . "\n" . TF::RESET . TF::ITALIC . TF::RED . '(Auto save)');
        $form->addButton(TF::BOLD . TF::DARK_RED . 'Delete island');
        return true;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    public function updateIslandSettings() : void {
        $form = new CustomForm(function(Player $p, array $d = null) : void {
            if ($d === null) {
                $this->overviewIsland();
                return;
            }

            if ((int)$d[0] === 0) $vec = new Vector3((int)$d[1], (int)$d[2], (int)$d[3]);
            elseif ($this->getSession()->getPlayer()->getLevel()->getFolderName() === $this->getSession()->getIsland()->getLevel()) $vec = $this->getSession()->getPlayer()->asVector3();
            else return; // TODO: Error
            if (!$vec->equals($this->getSession()->getIsland()->getStartCoord())) $this->getSession()->getIsland()->setStartCoord($vec);

            if ((int)$d[4] === 0) $vec = new Vector3((int)$d[5], (int)$d[6], (int)$d[7]);
            elseif ($this->getSession()->getPlayer()->getLevel()->getFolderName() === $this->getSession()->getIsland()->getLevel()) $vec = $this->getSession()->getPlayer()->asVector3();
            else return;
            if (!$vec->equals($this->getSession()->getIsland()->getEndCoord())) $this->getSession()->getIsland()->setEndCoord($vec);

            // TODO: Y offset validation
            if ($this->getSession()->getIsland()->getYOffset() !== (int)$d[8]) $this->getSession()->getIsland()->setYOffset((int)$d[8]);
        });
        $form->addDropdown(TF::BOLD . TF::GOLD . 'Start coordinate: ', [
            TF::BLUE . 'Update coordinate to: ',
            ($this->getSession()->getPlayer()->getLevel()->getFolderName() === $this->getSession()->getIsland()->getLevel() ? TF::BLUE : TF::GRAY) . 'Update coordinate to player position'
        ]);
        $vec = $this->getSession()->getIsland()->getStartCoord() ?? new Vector3(0, 0, 0);
        $form->addInput(TF::AQUA . 'X: ', (string)$vec->getFloorX(), (string)$vec->getFloorX());
        $form->addInput(TF::AQUA . 'Y: ', (string)$vec->getFloorY(), (string)$vec->getFloorY());
        $form->addInput(TF::AQUA . 'Z: ', (string)$vec->getFloorZ(), (string)$vec->getFloorZ());

        $form->addDropdown(TF::BOLD . TF::GOLD . 'End coordinate: ', [
            TF::BLUE . 'Update coordinate to: ',
            ($this->getSession()->getPlayer()->getLevel()->getFolderName() === $this->getSession()->getIsland()->getLevel() ? TF::BLUE : TF::GRAY) . 'Update coordinate to player position'
        ]);
        $vec = $this->getSession()->getIsland()->getEndCoord() ?? new Vector3(0, 0, 0);
        $form->addInput(TF::AQUA . 'X: ', (string)$vec->getFloorX(), (string)$vec->getFloorX());
        $form->addInput(TF::AQUA . 'Y: ', (string)$vec->getFloorY(), (string)$vec->getFloorY());
        $form->addInput(TF::AQUA . 'Z: ', (string)$vec->getFloorZ(), (string)$vec->getFloorZ());

        $form->addInput(TF::BOLD . TF::GOLD . 'Y offset: ', (string)$this->getSession()->getIsland()->getYOffset(), (string)$this->getSession()->getIsland()->getYOffset());

        $this->getSession()->getPlayer()->sendForm($form);
    }

    public function listRandoms(?\Closure $callback = null) : void {
        if ($this->getSession()->getIsland() === null) return;
        if (class_exists(SimpleForm::class)) {
            $f = new SimpleForm(function(Player $p, int $d = null) use ($callback) : void {
                if ($d === null) {
                    if (is_callable($callback)) $callback();
                    return;
                }
                if ($d <= count($this->getSession()->getIsland()->getRandoms()) or count($this->getSession()->getIsland()->getRandoms()) < 0x7fffffff) {
                    if ($this->getSession()->getIsland()->getRandomById($d) === null) $this->editRandom();
                    else $this->editRandom($d);
                }
            });
            foreach ($this->getSession()->getIsland()->getRandoms() as $i => $r) $f->addButton(TF::BOLD . TF::DARK_BLUE . $this->getSession()->getIsland()
                                                                                                                               ->getRandomLabel($i) . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(' . count($r->getAllElements()) . ' elements)');
            $f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Regex List');
            $f->addButton(count($this->getSession()->getIsland()
                                     ->getRandoms()) < 0x7fffffff ? TF::BOLD . TF::DARK_GREEN . 'New Regex' : TF::BOLD . TF::DARK_GRAY . 'Max limit reached' . "\n" . TF::RESET . TF::ITALIC . TF::GRAY . '(2147483647 regex)');
            $this->getSession()->getPlayer()->sendForm($f);
        } else {
            $this->getSession()->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Cannot edit random generation regex due to required virion dependency "libFormAPI"' . TF::ITALIC . TF::GRAY . '(https://github.com/Infernus101/FormAPI) ' .
                TF::RESET .
                TF::BOLD . TF::RED . 'is not installed. ' . TF::YELLOW . 'An empty regex has been added to the template island data, please edit it manually with an text editor!');
            $this->getSession()->getIsland()->addRandom(new RandomGeneration);
        }
    }

    public function editRandom(?int $regexid = null) : bool {
        if ($regexid === null and count($this->getSession()->getIsland()->getRandoms()) < 0x7fffffff) $regexid = $this->getSession()->getIsland()->addRandom(new RandomGeneration);
        // 2147483647, max limit of int tag value and random generation regex number
        elseif ($regexid === null) return false;
        $form = new SimpleForm(function(Player $p, int $d = null) use ($regexid) : void {
            if ($d === null) {
                $this->listRandoms();
                return;
            }
            $r = $this->getSession()->getIsland()->getRandomById($regexid);
            if ($r === null) {
                $this->getSession()->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'The target random generation regex has been removed!');
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
                    $this->getSession()->getPlayer()->getInventory()->addItem($r->getRandomGenerationItem($this->getSession()->getIsland()->getRandomSymbolicItem($regexid)));
                    $this->getSession()->getPlayer()->sendMessage(TF::BOLD . TF::YELLOW . 'The random generation block item has been added to your inventory');
                    break;

                case 4:
                    if (!class_exists(InvMenu::class)) {
                        $form = new ModalForm(function(Player $p, bool $d) use ($regexid) : void {
                            $this->editRandom($regexid);
                        });
                        $form->setTitle(TF::BOLD . TF::RED . 'Error');
                        $form->setContent(TF::BOLD . TF::RED . 'Cannot preview generation due to required virion "InvMenu v4"' . TF::ITALIC . TF::GRAY . '(https://github.com/Muqsit/InvMenu) ' . TF::RESET . TF::BOLD . TF::RED . 'is not installed!');
                        $this->getSession()->getPlayer()->sendForm($form);
                        break;
                    }
                    new GenerationPreviewSession($this->getSession(), $r, function() use ($regexid) : void {
                        $this->editRandom($regexid);
                    });
                    break;

                case 5:
                    $form = new ModalForm(function(Player $p, bool $d) use ($regexid) : void {
                        if ($d) {
                            $this->getSession()->getIsland()->removeRandomById($regexid);
                            $this->listRandoms();
                        } else $this->editRandom($regexid);
                    });
                    $form->setTitle(TF::BOLD . TF::DARK_RED . 'Delete Confirmation');
                    $form->setContent(TF::YELLOW . 'Are you sure to ' . TF::BOLD . TF::RED . 'remove' . TF::RESET . TF::YELLOW . ' random generation regex ' . TF::GOLD . '#' . $regexid .
                        (($label = $this->getSession()->getIsland()->getRandomLabel($regexid, true)) === null ? '' : TF::ITALIC . ' (' . $label . ')') . TF::RESET . TF::YELLOW .
                        ' from your current checked out island ' . TF::BOLD . TF::GOLD . '"' . $this->getSession()->getIsland()->getName() . '"' . TF::RESET . TF::YELLOW . ' and all the random generation blocks around the world? ' .
                        TF::BOLD .
                        TF::RED
                        . 'This action cannot be undo!'
                    );
                    $form->setButton1('gui.yes');
                    $form->setButton2('gui.no');
                    $this->getSession()->getPlayer()->sendForm($form);
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
        $this->getSession()->getPlayer()->sendForm($form);
        return true;
    }

    public function listRandomElements(RandomGeneration $regex) : void {
        $elements = $regex->getAllElements();
        $form = new SimpleForm(function(Player $p, int $d = null) use ($elements, $regex) : void {
            if ($d === null) {
                $rid = $this->getSession()->getIsland()->getRegexId($regex);
                if ($rid !== null) $this->editRandom($rid);
                return;
            }
            $elements = array_keys($elements);
            $element = $elements[$d] ?? null;
            if (!isset($element)) {
                $this->getSession()->submitBlockSession(function(Item $item) use ($regex) : void {
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
        $rid = $this->getSession()->getIsland()->getRegexId($regex);
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Elements of Regex' . (isset($rid) ? ' #' . $rid : ''));
        $totalchance = $regex->getTotalChance();
        foreach ($elements as $element => $chance) {
            $element = explode(':', $element);
            $item = Item::get((int)$element[0], (int)$element[1]);
            $form->addButton(TF::DARK_BLUE . $item->getVanillaName() . ' (' . $element[0] . ':' . $element[1] . ') ' . "\n" . TF::BLUE . ' Chance: ' . $chance . ' (' .

                $chance . ' / ' . ($totalchanceNonZero = $totalchance == 0 ? $chance : $totalchance) . ', ' . round($chance / $totalchanceNonZero * 100, 2) . '%%)');
        }
        $form->addButton(TF::BOLD . TF::DARK_GREEN . 'Add Element');
        $this->getSession()->getPlayer()->sendForm($form);
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
                $this->getSession()->submitBlockSession(function(Item $item) use ($regex, $id, $meta) : void {
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
        $this->getSession()->getPlayer()->sendForm($form);
    }

    protected function errorInvalidBlock(?\Closure $callback = null) : void {
        $form = new ModalForm($callback ?? null);
        $form->setTitle(TF::BOLD . TF::DARK_RED . 'Error');
        $form->setContent(TF::GOLD . 'Submitted item must be a valid block item!');
        $this->getSession()->getPlayer()->sendForm($form);
    }

    public function editRandomLabel(int $regexid) : void {
        $form = new CustomForm(function(Player $p, array $d = null) use ($regexid) : void {
            if ($d === null) {
                $this->editRandom($regexid);
                return;
            }
            $this->getSession()->getIsland()->setRandomLabel($regexid, (string)$d[0]);
        });
        $label = (string)$this->getSession()->getIsland()->getRandomLabel($regexid);
        $form->addInput(TF::BOLD . TF::GOLD . 'Label', $label, $label);
        $this->getSession()->getPlayer()->sendForm($form);
    }

    public function editRandomSymbolic(int $regexid) : void {
        $this->getSession()->submitBlockSession(function(Item $item) use ($regexid) : void {
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
                $this->getSession()->getPlayer()->sendForm($form);
            }
            $this->getSession()->getIsland()->setRandomSymbolic($regexid, $item->getId(), $item->getDamage());
        });
    }

    public function changeIslandLevel(?string $level = null) : void {
        if ($level === null) {
            return;
        }
        $is = $this->getSession()->getIsland();
        $form = new ModalForm(function(Player $p, bool $d) use ($is, $level) : void {
            if (!$d) return;
            $is->setLevel($level);
            $is->setStartCoord(null);
            $is->setEndCoord(null);
            $is->setSpawn(null);
            $is->setYOffset(null);
            $this->getSession()->getPlayer()->sendMessage(TF::YELLOW . 'Island level set to ' . TF::GOLD . '"' . $level . '"');
        });
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Change Confirmation');
        $form->setContent(TF::YELLOW . 'All the other settings of the template island will be ' . TF::BOLD . TF::RED . 'reset' . TF::RESET . TF::YELLOW . ' after changing island level, are you sure to proceed?');
        $form->setButton1('gui.yes');
        $form->setButton2('gui.no');
        $this->getSession()->getPlayer()->sendForm($form);
    }

}