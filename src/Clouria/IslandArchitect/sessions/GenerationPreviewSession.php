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
use muqsit\invmenu\InvMenu;
use pocketmine\utils\Random;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat as TF;
use pocketmine\level\generator\Generator;
use Clouria\IslandArchitect\IslandArchitect;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use Clouria\IslandArchitect\generator\properties\RandomGeneration;
use function in_array;
use function random_int;
use const INT32_MAX;
use const INT32_MIN;

class GenerationPreviewSession {

    public const ACTION_FRAME = 0;
    public const ACTION_RANDOM = 1;
    public const ACTION_SEED = 2;
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
    /**
     * @var \Closure|null
     */
    private $callback;

    public function __construct(PlayerSession $session, RandomGeneration $regex, ?\Closure $callback = null) {
        $this->session = $session;
        $this->regex = $regex;
        $this->setCallback($callback);
        $this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $this->getMenu()->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction) : void {
            $out = $transaction->getOut();
            if (($nbt = $out->getNamedTagEntry('action')) instanceof ByteTag) switch ($nbt->getValue()) {
                case self::ACTION_RANDOM:
                    $this->panelGeneration();
                    $this->getMenu()->getInventory()->sendContents($this->getSession()->getPlayer());
                    break;

                case self::ACTION_SEED:
                    $this->getSession()->getPlayer()->removeWindow($this->getMenu()->getInventory());
                    $transaction->then(function() : void {
                        $this->editSeed();
                    });
                    break;
            } elseif ($nbt = $out->getNamedTagEntry('element') instanceof ListTag) {
                $this->getSession()->getPlayer()->removeWindow($this->getMenu()->getInventory());
                $transaction->then(function() use ($nbt) : void {
                    if (!($id = $nbt[0]) instanceof ShortTag) return;
                    $meta = $nbt[1];
                    $this->getSession()->editRandomElement($this->getRegex(), (int)$id->getValue(), $meta instanceof ByteTag ? $meta->getValue() : 0);
                });
            }
        }));
        if (count($regex->getAllElements()) > 0) {
            $rid = $session->getIsland()->getRegexId($regex);
            $this->getMenu()->setName(TF::BOLD . TF::DARK_BLUE . 'Preview regex' . (isset($rid) ? ' #' . $rid : ''));
        } else $this->getMenu()->setName(TF::BOLD . TF::DARK_RED . 'Error: Regex is empty');
        $this->getMenu()->setInventoryCloseListener(function() : void {
            $rid = $this->getSession()->getIsland()->getRegexId($this->getRegex());
            if (isset($rid)) $this->getSession()->editRandom($rid);
            else $this->getSession()->listRandoms();
        });

        if (($seed = IslandArchitect::getInstance()->getConfig()->get('panel-default-seed', null)) === null) try {
            $seed = random_int(INT32_MIN, INT32_MAX);
        } catch (\Exception $e) {
            $seed = 1;
        }
        $this->noise = new Random((int)$seed);

        $this->panelInit();

        $this->getMenu()->send($session->getPlayer());
    }

    /**
     * @return InvMenu
     */
    public function getMenu() : InvMenu {
        return $this->menu;
    }

    protected function panelGeneration() : void {
        $i = Item::get(Item::EXPERIENCE_BOTTLE);
        $i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Next roll');
        $i->setNamedTagEntry(new ByteTag('action', self::ACTION_RANDOM));
        $this->getMenu()->getInventory()->setItem(18, $i, false);

        $inv = $this->getMenu()->getInventory();
        for ($slot = 0; $slot < 54; $slot++) {
            if (in_array($slot, [0, 9, 18, 27, 36, 45, 1, 10, 19, 28, 37, 46], true)) continue;

            $i = self::displayConversion($this->getRegex()->randomElementItem($this->getNoise()));
            $i->setCustomName(TF::RESET . $i->getVanillaName() . "\n\n      " . TF::YELLOW . 'Roll: ' . TF::BOLD . TF::GOLD . ++$this->rolls);
            $inv->setItem($slot, $i, false);
        }
    }

    protected static function displayConversion(Item $item, &$succeed = false) : Item {
        $succeed = false;
        $count = $item->getCount();
        $nbt = $item->getNamedTag();
        switch (true) {
            case $item->getId() === Item::AIR:
                $item = Item::get(217);
                $succeed = true;
                break;
        }
        $item->setCount($count);
        foreach ($nbt as $tag) $item->setNamedTagEntry($tag);
        return $item;
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
     * @param Random $noise
     */
    public function setNoise(Random $noise) : void {
        $this->rolls = 0;
        $this->noise = $noise;
    }

    /**
     * @return PlayerSession
     */
    public function getSession() : PlayerSession {
        return $this->session;
    }

    public function editSeed() : void {
        $form = new CustomForm(function(Player $p, array $d = null) : void {
            if ($d !== null and !empty($d[0] ?? null)) {
                $this->setNoise(new Random(empty(preg_replace('/[0-9-]+/i', '', $d[0])) ? (int)$d[0] : Generator::convertSeed($d[0])));
                $this->panelSeed();
                $this->panelGeneration();
            }
            $this->menu->send($this->getSession()->getPlayer());
        });
        $form->setTitle(TF::BOLD . TF::DARK_AQUA . 'Edit Seed');
        $form->addInput(TF::BOLD . TF::ITALIC . TF::GRAY . '(Empty box to discard change)', (string)$this->getNoise()->getSeed(), isset($this->random) ? (string)$this->random->getSeed() : '');
        $this->getSession()->getPlayer()->sendForm($form);
    }

    protected function panelSeed() : void {
        $i = Item::get(Item::SEEDS);
        $i->setCustomName(TF::RESET . TF::BOLD . TF::YELLOW . 'Change preview seed ' . TF::ITALIC . TF::GOLD . '(' . $this->getNoise()->getSeed() . ')');
        $i->setNamedTagEntry(new ByteTag('action', self::ACTION_SEED));
        $this->getMenu()->getInventory()->setItem(27, $i, false);
    }

    protected function panelInit() : void {
        $inv = $this->getMenu()->getInventory();

        $this->panelGeneration();
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
     * @return \Closure|null
     */
    public function getCallback() : ?\Closure {
        return $this->callback;
    }

    public function setCallback(?\Closure $callback) : void {
        $this->callback = $callback;
    }

}