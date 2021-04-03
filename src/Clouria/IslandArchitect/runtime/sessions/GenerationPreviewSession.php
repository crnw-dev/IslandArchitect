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
    utils\Random,
    utils\TextFormat as TF};

use muqsit\invmenu\InvMenu;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\runtime\RandomGeneration;

use function random_int;

use const INT32_MAX;
use const INT32_MIN;

class GenerationPreviewSession {

    /**
     * @var PlayerSession
     */
    private $session;
    /**
     * @var RandomGeneration
     */
    private $regex;

    /**
     * @var Random
     */
    private $noise;
    /**
     * @var InvMenu
     */
    private $menu;

    public function __construct(PlayerSession $session, RandomGeneration $regex) {
        $this->session = $session;
        $this->regex = $regex;
        $this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);

        if (($seed = IslandArchitect::getInstance()->getConfig()->get('panel-default-seed', null)) === null) try {
            $seed = random_int(INT32_MIN, INT32_MAX);
        } catch (\Exception $e) {
            $seed = 1;
        }
        $this->noise = new Random((int)$seed);

        $this->panelInit();
    }

    protected function panelInit() : void {
        $inv = $this->getMenu()->getInventory();

        $this->panelGeneration();
        $this->panelRandom();
        $this->panelSeed();

        // Frames
        $i = Item::get(Item::INVISIBLEBEDROCK);
        $i->setCustomName(TF::RESET);
        $i->setNamedTagEntry(new ByteTag('action', self::ACTION_FRAME));
        $inv->setItem(0, $i, false);
        $inv->setItem(45, $i, false);
        for ($slot=1; $slot <= 46; $slot+=9) $inv->setItem($slot, $i, false);

        // Reset noise action item button
    }

    protected function panelRandom() : void {
        $this->
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    /**
     * @return RandomGeneration
     */
    public function getRegex() : RandomGeneration {
        return $this->regex;
    }

    /**
     * @return Random
     */
    public function getNoise() : Random {
        return $this->noise;
    }

    /**
     * @return InvMenu
     */
    public function getMenu() : InvMenu {
        return $this->menu;
    }

}