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
    utils\Random,
    nbt\tag\ByteTag,
    utils\TextFormat as TF
};

use muqsit\invmenu\InvMenu;

use Clouria\IslandArchitect\{
    IslandArchitect,
    runtime\RandomGeneration
};

use function random_int;
use const INT32_MAX;
use const INT32_MIN;

class GenerationPreviewSession {

    protected $rolls = 0;
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

    public const ACTION_FRAME = 0;
    public const ACTION_RANDOM = 1;
    public const ACTION_SEED = 2;

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
        $inv->setItem(9, $i, false);
        $inv->setItem(36, $i, false);
        $inv->setItem(45, $i, false);
        for ($slot = 1; $slot <= 46; $slot += 9) $inv->setItem($slot, $i, false);
    }

    /**
     * @return InvMenu
     */
    public function getMenu() : InvMenu {
        return $this->menu;
    }

    protected function panelRandom() : void {
        $i = Item::get(Item::EXPERIENCE_BOTTLE, 0, max(100, $this->rolls));
        $i->setCustomName(TF::BOLD . TF::YELLOW . 'Next roll ' . TF::ITALIC . TF::GOLD . '(' . $this->rolls . ')');
        $i->setNamedTagEntry(new ByteTag('action', self::ACTION_RANDOM));
        $this->getMenu()->getInventory()->setItem(18, $i, false);
    }

    protected function panelSeed() : void {
        $i = Item::get(Item::SEEDS);
        $i->setCustomName(TF::BOLD . TF::YELLOW . 'Change preview seed ' . TF::ITALIC . TF::GOLD . '(' . $this->getNoise()->getSeed() . ')');
        $i->setNamedTagEntry(new ByteTag('action', self::ACTION_SEED));
        $this->getMenu()->getInventory()->setItem(27, $i, false);
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

}