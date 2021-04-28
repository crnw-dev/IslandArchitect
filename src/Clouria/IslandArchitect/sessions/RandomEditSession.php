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
use function explode;
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
     * RandomEditSession constructor.
     */
    public function __construct(PlayerSession $session, ?RandomGeneration $regex = null, ?int $id = null, ?int $meta = null) {
        $this->session = $session;
        $this->regex = $regex;
        if (isset($id)) $this->last_edited_element = $id . ':' . $meta;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    public function editRandom() : void {
        $p = $this->getSession()->getPlayer();
        $is = $this->getSession()->getIsland();
        $regexid = $is->getRegexId($this->getRegex());
        $form = new CustomForm(function(Player $p, array $d = null) : void {
        });
        $totalchance = $this->getRegex()->getTotalChance();
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Modify Regex #' . $regexid);

        $i = -1;
        foreach ($is->getRandoms() as $i => $random) $rs[] = TF::DARK_BLUE . '#' . $i . (($l = $is->getRandomLabel($i, true)) === null ? '' : TF::BLUE . ' ' . $l . '');
        $rs[] = TF::DARK_GRAY . '#' . $i . TF::BOLD . TF::DARK_GREEN . ' Create new regex';
        $form->addDropdown(TF::BOLD . TF::GOLD . 'Select regex to modify:', $rs, $regexid ?? 0);
        if (!$this->isCreateNewElement()) {
            foreach ($this->getRegex()->getAllElements() as $element => $chance) {
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
            if ($item !== null) $elementoption = array_search($item->getId() . ':' . $item->getDamage(), $this->getRegex()->getAllElements(), true);
            $form->addDropdown(TF::BOLD . TF::GOLD . 'Select element to edit:', $es, (!isset($elementoption) or $elementoption === false) ? count($this->getRegex()->getAllElements()) + 1 : $elementoption);
        } else $form->addInput(TF::BOLD . TF::GOLD . 'Element ID:', '<ID / NamespaceID>:[meta]');
        $item = $this->getLastEditedElementAsItem();
        if ($item !== null) $chance = $this->getRegex()->getElementChance($item->getId(), $item->getDamage());
        $form->addInput(TF::BOLD . TF::GOLD . 'Element chance:', 'Element chance', (string)($chance ?? ''));
        $form->addInput(TF::BOLD . TF::GOLD . 'Regex label:', $is->getRandomLabel($regexid), $is->getRandomLabel($regexid, true) ?? '');
        $p->sendForm($form);
    }

    /**
     * @return RandomGeneration|null
     */
    public function getRegex() : ?RandomGeneration {
        return $this->regex;
    }

    public function getLastEditedElementAsItem() : ?Item {
        if (!isset($this->last_edited_element)) return null;
        try {
            return ItemFactory::fromStringSingle($this->last_edited_element);
        } catch (\InvalidArgumentException $err) {
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isCreateNewElement() : bool {
        return $this->create_new_element;
    }

}