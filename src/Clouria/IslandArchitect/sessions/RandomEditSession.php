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
            $pickedregex = (int)$d[0];
            if ($pickedregex !== $regexid and ($this->getRegex() !== null and $regexid === null and $pickedregex !== count($regs))) {
                if (isset($regs[$pickedregex])) $this->setRegex($regexid);
                else {
                    $regex = new RandomGeneration;
                    $pickedregex = $is->addRandom($regex);
                    $this->setRegex($regex);
                }
            }
            $is->setRandomLabel($pickedregex, (string)$d[3]);
            if (is_int($d[1])) switch ($d[1]) {

                case count($elements):
                    break;

                case count($elements) + 1:
                    $this->create_new_element = true;
                    break;

                case count($elements) + 2:
                    $this->getSession()->submitBlockSession(function(Item $item) : void {
                        $this->getRegex()->setElementChance($item->getId(), $item->getDamage(), $item->getCount());
                        $this->last_edited_element = $item->getId() . ':' . $item->getDamage();
                        $this->editRandom();
                    });
                    return;

                default:
                    $element = array_keys($elements)[$d[1]] ?? null;
                    if (!isset($element)) break;
                    $this->last_edited_element = $element;
                    $element = explode(':', $element);
                    $this->getRegex()->setElementChance($element[0], $element[1], (int)$d[2]);
                    break;
            } else {
                $item = ItemFactory::fromString($d[1], true);
                $chance = explode(',', str_replace(', ', ',', $d[2]));
                if (count($chance) !== 1 and count($chance) !== count($item)) return; // TODO: Error
                if (count($item) === 1) $this->last_edited_element = $item[0]->getId() . ':' . $item[0]->getDamage();
                foreach ($item as $i => $si) $this->getRegex()->setElementChance($si->getId(), $si->getDamage(), count($chance) === 1 ? (int)$chance[0] : (int)($chance[$i] ?? 0));
            }
            $this->editRandom();
        });
        if ($this->getRegex() !== null) $totalchance = $this->getRegex()->getTotalChance();
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Modify Regex #' . $regexid);

        $i = -1;
        foreach ($regs as $i => $random) $rs[] = TF::DARK_BLUE . '#' . $i . (($l = $is->getRandomLabel($i, true)) === null ? '' : TF::BLUE . ' ' . $l . '');
        $rs[] = TF::DARK_GRAY . '#' . $i . TF::BOLD . TF::DARK_GREEN . ' Create new regex';
        if ($this->getRegex() !== null and $regexid === null) $rs[] = TF::ITALIC . TF::DARK_GRAY . 'External regex';
        $form->addDropdown(TF::BOLD . TF::GOLD . 'Select regex to modify:', $rs, $regexid ?? 0);
        if (!$this->isCreateNewElement()) {
            if (isset($totalchance)) foreach ($elements as $element => $chance) {
                $element = explode(':', $element);
                $item = Item::get((int)$element[0], (int)$element[1]);
                $totalchanceNonZero = $totalchance == 0 ? $chance : $totalchance;
                $es[] = TF::DARK_BLUE . $item->getVanillaName() . TF::BLUE . ' (' . $item->getId() . ':' . $item->getDamage() . ')' .
                    TF::DARK_BLUE . 'Chance: ' . $chance . TF::BLUE . ' (' . round($chance / $totalchanceNonZero * 100, 2) . '%%)';
            }
            $es[] = TF::BOLD . TF::DARK_GRAY . 'Do nothing';
            $es[] = TF::BOLD . TF::DARK_GREEN . 'Enter element ID manually (Create element)';
            $es[] = TF::BOLD . TF::DARK_AQUA . 'Add element from inventory';
            $item = $this->getLastEditedElementAsItem();
            if ($item !== null) $elementoption = array_search($item->getId() . ':' . $item->getDamage(), $elements, true);
            $form->addDropdown(TF::BOLD . TF::GOLD . 'Select element to edit:', $es, (!isset($elementoption) or $elementoption === false) ? count($elements) + 1 : $elementoption);
        } else $form->addInput(TF::BOLD . TF::GOLD . 'Element ID:', '<ID / NamespaceID>:[meta], ...');
        $item = $this->getLastEditedElementAsItem();
        if ($item !== null) $chance = $this->getRegex()->getElementChance($item->getId(), $item->getDamage());
        $form->addInput(TF::BOLD . TF::GOLD . 'Element chance:', '<chance>, ...', (string)($chance ?? ''));
        $form->addInput(TF::BOLD . TF::GOLD . 'Regex label:', $is->getRandomLabel($regexid) ?? 'Leave here empty to remove label', $is->getRandomLabel($regexid, true) ?? '');
        $p->sendForm($form);
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
    public function setRegex(?RandomGeneration $regex) : void {
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

}