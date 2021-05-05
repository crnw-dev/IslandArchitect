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

namespace Clouria\IslandArchitect\internal;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\command\Command;
use jojoe77777\FormAPI\ModalForm;
use pocketmine\utils\TextFormat as TF;
use pocketmine\command\CommandSender;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\generator\TemplateIsland;
use Clouria\IslandArchitect\sessions\RandomEditSession;
use Clouria\IslandArchitect\generator\tasks\IslandDataLoadTask;
use Clouria\IslandArchitect\events\TemplateIslandCheckOutEvent;
use function strtolower;
use function class_exists;

class IslandArchitectCommand extends Command {

    public function __construct() {
        parent::__construct('island-architect', 'Command of the IslandArchitect plugin', '/island-architect help', ['ia', 'isarch']);
        $this->setPermission('island-architect.cmd');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
        if (!$sender->hasPermission('island-architect.cmd')) {
            $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TF::RED . "%commands.generic.notFound"));
            return;
        }
        $args[0] = strtolower($args[0] ?? 'help');
        if (!$sender instanceof Player) $sender->sendMessage(TF::BOLD . TF::RED . 'Please use the command in-game!');
        else switch ($args[0]) {
            case 'pos':
            case 'coord':
            case 'p':
                $s = IslandArchitect::getInstance()->getSession($sender);
                if (PlayerSession::errorCheckOutRequired($sender, $s)) break;
                if (isset($args[2]) and isset($args[3]) and isset($args[4])) $vec = new Vector3((int)$args[2], (int)$args[3], (int)$args[4]);
                else $vec = $sender->asVector3();
                $w = $s->getIsland()->getLevel();
                if ($w !== null) {
                    if ($w !== $sender->getLevel()->getFolderName()) {
                        $sender->sendMessage(TF::BOLD . TF::RED . 'You can only run this command in the same world as the island: ' . $w);
                        break;
                    }
                } else $s->getIsland()->setLevel($sender->getLevel()->getFolderName());

                if ((isset($args[1]) and ($args[1] === '1' or $args[1] === 'sc')) or $s->getIsland()->getStartCoord() === null) {
                    $sender->sendMessage(TF::YELLOW . 'Start coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
                    $s->getIsland()->setStartCoord($vec);
                } elseif ((isset($args[1]) and ($args[1] === '2' or $args[1] === 'ec')) or $s->getIsland()->getEndCoord() === null) {
                    $sender->sendMessage(TF::YELLOW . 'End coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
                    $s->getIsland()->setEndCoord($vec);
                } elseif ($s->getIsland()->getStartCoord()->distance($vec) <= $s->getIsland()->getEndCoord()->distance($vec)) {
                    $sender->sendMessage(TF::YELLOW . 'Start coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
                    $s->getIsland()->setStartCoord($vec);
                } else {
                    $sender->sendMessage(TF::YELLOW . 'End coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
                    $s->getIsland()->setEndCoord($vec);
                }
                break;

            case 'island':
            case 'checkout':
            case 'check-out':
            case 'i':
                if (!isset($args[1]) or preg_match('/[^0-9a-z-_]+/', $args[1])) {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'Invalid island name or island name argument missing!');
                    break;
                }
                if (!$sender->hasPermission('island-architect.convert') and !$sender->hasPermission('island-architect.convert.' . $args[1])) {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'You don\' have permission to access this island!');
                    break;
                }
                $checkout = function() use ($sender, $args) {
                    $time = microtime(true);
                    $sender->sendMessage(TF::YELLOW . 'Loading island ' . TF::GOLD . '"' . $args[1] . '"...');
                    $callback = function(?TemplateIsland $is, string $filepath) use ($sender, $time) : void {
                        if (!$sender->isOnline()) return;
                        if (!isset($is)) {
                            $is = new TemplateIsland(basename($filepath, '.json'));
                            IslandArchitect::getInstance()->addDefaultRandomRegex($is);
                            $is->noMoreChanges();
                            $sender->sendMessage(TF::BOLD . TF::GOLD . 'Created' . TF::GREEN . ' new island "' . $is->getName() . '"!');
                        } else $sender->sendMessage(TF::BOLD . TF::GREEN . 'Checked out island "' . $is->getName() . '"! ' . TF::ITALIC . TF::GRAY . '(' . round(microtime(true) - $time, 2) . 's)');
                        $s = IslandArchitect::getInstance()->getSession($sender, true);
                        $s->checkOutIsland($is);
                        $ev = new TemplateIslandCheckOutEvent($s, $is);
                        $ev->call();
                    };
                    foreach (IslandArchitect::getInstance()->getSessions() as $ss) if (
                        ($i = $ss->getIsland()) !== null and
                        $i->getName() === $args[1]
                    ) {
                        $callback($i, IslandArchitect::getInstance()->getDataFolder() . $i->getName());
                        return;
                    }
                    $task = new IslandDataLoadTask($args[1], $callback);
                    Server::getInstance()->getAsyncPool()->submitTask($task);
                };
                if (($s = IslandArchitect::getInstance()->getSession($sender)) !== null and $s->getIsland() !== null and class_exists(ModalForm::class)) {
                    $f = new ModalForm(function(Player $p, bool $d) use ($checkout, $s) : void {
                        if (!$d) return;
                        $s->saveIsland();
                        $checkout();
                    });
                    $f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Switch Island');
                    $f->setContent(TF::YELLOW . 'You have already checked out an island. ' . TF::GOLD . 'If you choose to proceed, all the changes you do to this island will be ' . TF::BOLD . TF::GREEN . 'save' . TF::RESET . TF::GOLD . ' before switching to the new one!');
                    $f->setButton1('gui.yes');
                    $f->setButton2('gui.no');
                    $sender->sendForm($f);
                } else $checkout();
                break;

            case 'random':
            case 'regex':
            case 'r':
                $s = IslandArchitect::getInstance()->getSession($sender);
                if (PlayerSession::errorCheckOutRequired($sender, $s)) break;
                if (isset($args[1])) {
                    if (empty(preg_replace('/[0-9]+/i', '', $args[1]))) new RandomEditSession($s, $s->getIsland()->getRandomById((int)$args[1]));
                    else foreach ($s->getIsland()->getRandomLabels() as $rid => $label) if (stripos($label, $args[1]) !== false) {
                        new RandomEditSession($s, $s->getIsland()->getRandomById($rid));
                        break 2;
                    }
                }
                new RandomEditSession($s);
                break;

            case 'export':
            case 'convert':
            case 'e':
                if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
                $s->exportIsland();
                break;

            case 'setspawn':
            case 'spawn':
            case 's':
                if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
                if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
                $vec = $vec ?? $sender->asPosition();
                if (($w = $s->getIsland()->getLevel()) !== null) if ($w !== $vec->getLevel()->getFolderName()) {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'You can only run this command in the same world as the island: ' . $w);
                    break;
                } else $s->getIsland()->setLevel($vec->getLevel()->getFolderName());
                $sender->sendMessage(TF::YELLOW . 'Island world spawn set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
                $s->getIsland()->setSpawn($vec);
                break;

            case 'level':
            case 'world':
            case 'l':
                if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
                if (isset($args[1])) {
                    $dir = scandir(Server::getInstance()->getDataPath() . 'worlds/');
                    unset($dir[array_search('.', $dir, true)]);
                    unset($dir[array_search('..', $dir, true)]);
                    if (!in_array($args[1], $dir, true)) {
                        $sender->sendMessage(TF::BOLD . TF::RED . 'Level does not exists!');
                        break;
                    }
                    $level = $args[1];
                } elseif ($s->getIsland()->getLevel() !== $sender->getLevel()->getFolderName()) $level = $sender->getLevel()->getFolderName();
                else $level = null;
                $s->changeIslandLevel($level);
                break;

            case 'yoffset':
            case 'y-offset':
            case 'y':
                if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
                if (!isset($args[1]) or (int)$args[1] < 0) {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'Please enter a valid Y offset value!');
                    break;
                }
                if (($sc = $s->getIsland()->getStartCoord()) !== null and ($ec = $s->getIsland()
                                                                                   ->getEndCoord()) !== null) if ((int)$args[1] + (max($sc->getFloorY(), $ec->getFloorY()) - min($sc->getFloorY(), $ec->getFloorY())) > Level::Y_MAX) {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'Please enter a valid Y offset value!');
                    break;
                }
                $s->getIsland()->setYOffset((int)$args[1]);
                $sender->sendMessage(TF::YELLOW . 'Island Y offset set to ' . TF::GOLD . $args[1]);
                break;

            default:
                $cmds[] = 'island <Island data file name: string> ' . TF::ITALIC . TF::GRAY . '(Check out or create an island)';
                $cmds[] = 'pos [1|2] [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the start / end coordinate of the island)';
                $cmds[] = 'random [Random regex ID: int|Random regex label: string] ' . TF::ITALIC . TF::GRAY . '(Setup random blocks generation)';
                $cmds[] = 'setspawn ' . TF::ITALIC . TF::GRAY . '(Set the island world spawn)';
                $cmds[] = 'yoffset <Offset value> ' . TF::ITALIC . TF::GRAY . '(Update the level of the island)';
                $cmds[] = 'level [Level folder name] ' . TF::ITALIC . TF::GRAY . '(Update the level of the island)';
                $cmds[] = 'export ' . TF::ITALIC . TF::GRAY . '(Export the checked out island into template island data file)';
                $sender->sendMessage(TF::BOLD . TF::GOLD . 'Available subcommands: ' . ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $cmds ?? ['help']));
                break;
        }
    }
}