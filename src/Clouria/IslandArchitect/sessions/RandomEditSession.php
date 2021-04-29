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
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use function is_int;
use function explode;
use function array_keys;
use function str_replace;
use function array_search;

class RandomEditSession {
    /**
     * @var null|string
     */
    protected $last_edited_element = null;

    /**
     * @var bool
     */
    protected $create_new_element = false;
    /**
     * @var PlayerSession
     */
    private $session;
    /**
     * @var RandomGeneration|null
     */
    private $regex;
    /**
     * @var \Closure|null
     */
    private $callback;
    /**
     * @var bool
     */
    protected $error_invalid_item = false;
    /**
     * @var bool
     */
    protected $error_bulk_count_mismatch = false;

    /**
     * @return \Closure|null
     */
    public function getCallback() : ?\Closure {
        return $this->callback;
    }

    /**
     * RandomEditSession constructor.
     */
    public function __construct(PlayerSession $session, ?RandomGeneration $regex = null, ?int $id = null, ?int $meta = null, ?\Closure $callback = null) {
        // TODO: Display error if no FormAPI installed
        $this->session = $session;
        $this->regex = $regex;
        if (isset($id)) $this->last_edited_element = $id . (isset($meta) ? ':' . $meta : '');
        $this->callback = $callback;
        $this->editRandom();
    }

    public function editRandom() : void {
        $p = $this->getSession()->getPlayer();
        $is = $this->getSession()->getIsland();
        if ($this->getRegex() !== null) $regexid = $is->getRegexId($this->getRegex());
        else $regexid = null;
        $regs = $is->getRandoms();
        if ($this->getRegex() !== null) $elements = $this->getRegex()->getAllElements();
        else $elements = [];
        $form = new CustomForm(function(Player $p, array $d = null) use ($regexid, $regs, $is, $elements) : void {
            if ($d === null) {
                if ($this->getCallback() !== null) $this->getCallback()();
                return;
            }
            $pickedregex = (int)$d[1];
            $orireg = $this->getRegex();
            if ($pickedregex !== $regexid or ($this->getRegex() === null and $pickedregex !== count($regs))) {
                $regchanged = true;
                if (isset($regs[$pickedregex])) {
                    $this->setRegex($regs[$pickedregex]);
                } else {
                    $regex = new RandomGeneration;
                    $pickedregex = $is->addRandom($regex);
                    $this->setRegex($regex);
                }
            }
            if (!isset($regchanged)) $is->setRandomLabel($pickedregex, $d[($this->last_edited_element !== null or $this->isCreateNewElement()) ? 4 : 3]);
            if ($orireg !== null) if (is_int($d[2])) switch ($d[2]) {

                case count($elements):
                    $this->create_new_element = true;
                    break;

                case count($elements) + 1:
                    $this->getSession()->submitBlockSession(function(Item $item) : void {
                        $this->getRegex()->setElementChance($item->getId(), $item->getDamage(), $item->getCount());
                        $this->last_edited_element = $item->getId() . ':' . $item->getDamage();
                        $this->editRandom();
                    });
                    return;

                case count($elements) + 2:
                    if (isset($regexid)) $this->removeConfirmation();
                    else $this->editRandom();
                    return;

                default:
                    $element = array_keys($elements)[$d[2]] ?? null;
                    if (!isset($element)) break;
                    $ori = $this->last_edited_element;
                    $this->last_edited_element = $element;
                    $element = explode(':', $element);
                    if ($ori !== null or $this->isCreateNewElement()) $this->getRegex()->setElementChance((int)$element[0], (int)$element[1], (int)$d[3]);
                    break;
            } else {
                try {
                    $item = ItemFactory::fromString($d[2], true);
                } catch (\InvalidArgumentException $err) {
                    $this->error_invalid_item = true;
                    $this->last_edited_element = $d[2];
                    $this->editRandom();
                    return;
                }
                $chance = explode(',', str_replace(', ', ',', $d[3]));
                if (count($chance) !== 1 and count($chance) !== count($item)) {
                    $this->error_bulk_count_mismatch = true;
                    $this->last_edited_element = $d[2];
                    $this->editRandom();
                    return;
                }
                if (count($item) === 1) $this->last_edited_element = $item[0]->getId() . ':' . $item[0]->getDamage();
                else $this->last_edited_element = null;
                foreach ($item as $i => $si) $this->getRegex()->setElementChance($si->getId(), $si->getDamage(), count($chance) === 1 ? (int)$chance[0] : (int)($chance[$i] ?? 0));
                $this->create_new_element = false;
            }
            $this->editRandom();
        });
        if ($this->getRegex() !== null) $totalchance = $this->getRegex()->getTotalChance();
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Modify Regex #' . $regexid);

        $i = -1;
        foreach ($regs as $i => $random) $rs[] = TF::DARK_BLUE . '#' . $i . (($l = $is->getRandomLabel($i, true)) === null ? '' : TF::BLUE . ' ' . $l . '');
        $rs[] = TF::DARK_GRAY . '#' . ($i + 1) . TF::BOLD . TF::DARK_GREEN . ' Create new regex';
        if ($this->getRegex() !== null and $regexid === null) $rs[] = TF::ITALIC . TF::DARK_GRAY . 'External regex';
        $msg = '';
        if ($this->error_bulk_count_mismatch) $msg .= TF::BOLD . TF::RED . 'Error: The count of given chance does not match the count of given item IDs!' . "\n";
        if ($this->error_invalid_item) $msg .= TF::BOLD . TF::RED . 'Error: Invalid item ID given!' . "\n";
        if (isset($this->last_edited_element) and !isset($elements[$this->last_edited_element])) {
            $ri = $this->getLastEditedElementAsItem();
            if ($ri !== null) $msg .= TF::YELLOW . 'Element removed: ' . TF::BOLD . TF::GOLD . $ri->getVanillaName() . ' (' . $ri->getId() . ':' . $ri->getDamage() . ')';
        }
        $form->addLabel($msg);
        $form->addDropdown(TF::BOLD . TF::GOLD . 'Select regex to modify:', $rs, $regexid ?? 0);
        $li = $this->getLastEditedElementAsItem();
        if (!$this->isCreateNewElement()) {
            if (isset($totalchance)) foreach ($elements as $element => $chance) {
                $element = explode(':', $element);
                $item = Item::get((int)$element[0], (int)$element[1]);
                $totalchanceNonZero = $totalchance == 0 ? $chance : $totalchance;
                $es[] = TF::DARK_BLUE . $item->getVanillaName() . ' (' . $item->getId() . ':' . $item->getDamage() . ') ' .
                    TF::BLUE . 'Chance: ' . $chance . ' (' . round($chance / $totalchanceNonZero * 100, 2) . '%%)';
            }
            $es[] = TF::BOLD . TF::GREEN . 'Enter element ID manually';
            $es[] = TF::BOLD . TF::AQUA . 'Add element from inventory';
            if (isset($regexid)) $es[] = TF::BOLD . TF::RED . 'Remove regex';
            if ($li !== null) $searched = array_search($li->getId() . ':' . $li->getDamage(), array_keys($elements), true);
            if ($this->getRegex() !== null) $form->addDropdown(TF::BOLD . TF::GOLD . 'Select an action or element to edit:', $es, (!isset($searched) or $searched === false) ? count($elements) + 1 : $searched);
        } else $form->addInput(TF::BOLD . TF::GOLD . 'Element ID:', '<ID / NamespaceID>:[meta], ...', $this->last_edited_element ?? '');
        if ($this->getRegex() !== null and $li !== null) $chance = $this->getRegex()->getElementChance($li->getId(), $li->getDamage());
        if ($this->last_edited_element !== null or $this->isCreateNewElement()) $form->addInput(TF::BOLD . TF::GOLD . 'Element chance:', '<chance>, ...', (string)($chance ?? ''));
        $l = $is->getRandomLabel($regexid, true);
        if ($this->getRegex() !== null) $form->addInput(TF::BOLD . TF::GOLD . 'Regex label:', $l ?? 'Leave here empty to remove label', $l ?? '');
        $p->sendForm($form);
        $this->resetErrorFlags();
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return RandomGeneration|null
     */
    public function getRegex() : ?RandomGeneration {
        return $this->regex;
    }

    /**
     * @param RandomGeneration|null $regex
     */
    public function setRegex(?RandomGeneration $regex = null) : void {
        $this->regex = $regex;
    }

    /**
     * @return bool
     */
    public function isCreateNewElement() : bool {
        return $this->create_new_element;
    }

    public function getLastEditedElementAsItem() : ?Item {
        if (!isset($this->last_edited_element)) return null;
        try {
            return ItemFactory::fromStringSingle($this->last_edited_element);
        } catch (\InvalidArgumentException $err) {
        }
        return null;
    }

    protected function resetErrorFlags() : void {
        $this->error_invalid_item = false;
        $this->error_bulk_count_mismatch = false;
    }

    public function removeConfirmation() : void {
        $is = $this->getSession()->getIsland();
        if ($this->getRegex() === null) {
            $this->editRandom();
            return;
        }
        $form = new ModalForm(function(Player $p, bool $d) use ($is) : void {
            if (!$d) {
                $this->editRandom();
                return;
            }
            $rid = $is->getRegexId($this->getRegex());
            if ($rid !== null) $is->removeRandomById($rid);
            $this->setRegex();
            $this->editRandom();
        });
        $form->setTitle(TF::BOLD . TF::DARK_RED . 'Regex Remove Confirmation');
        $rid = $is->getRegexId($this->getRegex());
        $form->setContent(TF::YELLOW . 'Are you sure to remove random generation regex ' . TF::BOLD . TF::GOLD . (isset($rid) ? '#' . $rid . ($is->getRandomLabel($rid, true) !== null ? ' (' . $is->getRandomLabel($rid) . ')' : '') : 'EXTERNAL REGEX') .
            '? ' . TF::RED
            . 'This action cannot be undone!');
        $form->setButton1('gui.yes');
        $form->setButton2('gui.no');
        $this->getSession()->getPlayer()->sendForm($form);
    }

}