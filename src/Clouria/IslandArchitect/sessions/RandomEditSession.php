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

class RandomEditSession {
    /**
     * @var null|int[]
     */
    protected $last_edited_element = null;
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
        if (isset($id)) $this->last_edited_element = [$id, $meta];
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
            if (!empty($d[1])) $this->last_edited_element = [$d[1], $d[2]];
            switch ((int)$d[5]) {

                case 1:
                case 2:
                    $string = str_replace(', ', ',', $d[1]);
                    if (!empty($string)) {
                        if (strpos($string, ',') !== false) {
                            if (preg_match('/[^0-9,:]+/i', $string)) try {
                                $items = ItemFactory::fromString($string, true);
                                foreach ($items as $item) {
                                    $bulkitem[] = $item->getId();
                                    if ($item->getDamage() !== 0) $bulkmeta[] = $item->getDamage();
                                }
                            } catch (\InvalidArgumentException $err) {
                                break; // TODO: Display error message
                            } else {
                                $ids = explode(',', $string);
                                foreach ($ids as $id) {
                                    $id = explode(':', $id);
                                    $bulkitem[] = (int)$id[0];
                                    if (isset($id[1])) $bulkmeta[] = (int)$id[1];
                                }
                            }
                            if (!isset($bulkmeta)) {
                                $bulkmeta = explode(',', str_replace(' ', '', $d[2]));
                                if (count($bulkmeta) > 1 and count($bulkmeta) !== count($bulkitem ?? [])) break; // TODO: Error
                            }
                            $bulkchance = explode(',', str_replace(' ', '', $d[3]));
                            if (count($bulkchance) > 1 and count($bulkchance) !== count($bulkitem ?? [])) break; // TODO: Error

                            foreach ($bulkitem ?? [] as $i => $id) $this->getRegex()->setElementChance(
                                (int)$id,
                                $meta = (int)($bulkmeta[$i] ?? $bulkmeta[0] ?? 0),
                                (int)($bulkmeta[$i] ?? $bulkmeta[0] ?? $this->getRegex()->getElementChance((int)$id, $meta))
                            );
                        } elseif ($this->getRegex()->getElementChance((int)$string, (int)$d[2]) !== (int)$d[3]) $this->getRegex()->setElementChance((int)$string, (int)$d[2], (int)$d[3]);
                    }
                    if ((int)$d[5] === 0) $this->listRandoms();
                    else $this->editRandom();
                    break;
            }
        });
        $totalchance = $this->getRegex()->getTotalChance();
        foreach ($this->getRegex()->getAllElements() as $element => $chance) {
            $element = explode(':', $element);
            $totalchanceNonZero = $totalchance == 0 ? $chance : $totalchance;
            $elements[] = (new Item((int)$element[0], (int)$element[1]))->getVanillaName() . ' (' . (int)$element[0] . ':' . (int)$element[1] . '): ' . TF::BOLD . TF::GOLD . $chance . ' (' . round($chance / $totalchanceNonZero * 100, 2)
                . '%%)';
        }
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Modify Regex #' . $regexid);

        $i = -1;
        foreach ($is->getRandoms() as $i => $random) $rs[] = TF::DARK_BLUE . '#' . $i . (($l = $is->getRandomLabel($i, true)) === null ? '' : TF::BLUE . ' ' . $l . '');
        $rs[] = TF::DARK_GRAY . '#' . $i . TF::BOLD . TF::DARK_GREEN . ' Create new regex';
        $form->addDropdown(TF::BOLD . TF::AQUA . 'Select regex to modify', $rs,);
        $form->addInput(TF::BOLD . TF::GOLD . 'Element item ID:', 'Element item ID', isset($this->last_edited_element) ? (string)$this->last_edited_element[0] : '');
        $form->addInput(TF::AQUA . 'Element item meta:', 'Element item meta', isset($this->last_edited_element) ? (string)($this->last_edited_element[1] ?? 0) : '');
        $form->addInput(TF::BOLD . TF::GOLD . 'Element chance:', 'Element chance', isset($this->last_edited_element) ?
            (string)(preg_match('/[0-9]+/i', $this->last_edited_element[0]) and ($chance = $this->getRegex()->getElementChance((int)$this->last_edited_element[0], (int)$this->last_edited_element[1])) === 0 ? '' : $chance) : '');
        $form->addInput(TF::BOLD . TF::GOLD . 'Regex label:', $is->getRandomLabel($regexid), $is->getRandomLabel($regexid, true) ?? '');
        $form->addDropdown(TF::AQUA . 'Action:', [
            TF::BOLD . TF::DARK_GREEN . 'Done',
            TF::DARK_BLUE . 'Apply',
            TF::BLUE . 'Add element from inventory',
            TF::BLUE . 'Update regex symbolic',
            TF::DARK_RED . 'Remove regex'
        ]);
        $p->sendForm($form);
    }

    /**
     * @return RandomGeneration|null
     */
    public function getRegex() : ?RandomGeneration {
        return $this->regex;
    }

    public function getLastEditedElementId() : ?int {
        if (isset($this->last_edited_element[0])) return (int)$this->last_edited_element[0];
        return null;
    }

    public function getLastEditedElementMeta() : ?int {
        if (isset($this->last_edited_element[0])) return (int)$this->last_edited_element[0];
        return null;
    }

}