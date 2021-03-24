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

namespace Clouria\IslandArchitect;

use jojoe77777\FormAPI\ModalForm;
use pocketmine\{
    Player,
    Server,
    utils\Utils,
    level\Position,
    command\Command,
    utils\TextFormat as TF,
    command\CommandSender};
use Clouria\IslandArchitect\{
    runtime\TemplateIsland,
    conversion\IslandDataLoadTask,
    runtime\sessions\PlayerSession,
    runtime\sessions\InvMenuSession,
    customized\CustomizableClassTrait,
    events\TemplateIslandCheckOutEvent};
use function strtolower;
use function class_exists;

class IslandArchitectCommand extends Command {
    use CustomizableClassTrait;

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
			case 'pos1':
			case 'p1':
			case '1':
				if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
				if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
				$vec = $vec ?? $sender->asPosition();
				if (($w = $s->getIsland()->getLevel()) !== null) {
					if ($w !== $vec->getLevel()->getFolderName()) {
					$sender->sendMessage(TF::BOLD . TF::RED . 'You can only run this command in the same world as the island: ' . $w);
						break;
					}
				} else $s->getIsland()->setLevel($vec->getLevel()->getFolderName());
				$sender->sendMessage(TF::YELLOW . 'Start coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setStartCoord($vec);
				break;

			case 'pos2':
			case 'p2':
			case '2':
				if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
				if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
				$vec = $vec ?? $sender->asPosition();
				if (($w = $s->getIsland()->getLevel()) !== null) if ($w !== $vec->getLevel()->getFolderName()) {
					$sender->sendMessage(TF::BOLD . TF::RED . 'You can only run this command in the same world as the island: ' . $w);
					break;
				} else $s->getIsland()->setLevel($vec->getLevel()->getFolderName());
				$sender->sendMessage(TF::YELLOW . 'End coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setEndCoord($vec);
				break;

			case 'island':
			case 'checkout':
			case 'check-out':
			case 'i':
				if (!isset($args[1]) or !empty(preg_replace('/[0-9a-z-_]+/i', '', $args[1]))) {
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
                    $callback = function (?TemplateIsland $is, string $filepath) use ($sender, $time) : void {
                        if (!$sender->isOnline()) return;
                        if (!isset($is)) {
                            $is = new TemplateIsland(basename($filepath, '.json'));
                            $sender->sendMessage(TF::BOLD . TF::GOLD . 'Created' . TF::GREEN . ' new island "' . $is->getName() . '"!');
                        } else $sender->sendMessage(TF::BOLD . TF::GREEN . 'Checked out island "' . $is->getName() . '"! ' . TF::ITALIC . TF::GRAY . '(' . round(microtime(true) - $time, 2) . 's)');
                        $s = IslandArchitect::getInstance()->getSession($sender, true);
                        $ev = new TemplateIslandCheckOutEvent($s, $is);
                        $ev->call();
                        if ($ev->isCancelled()) return;
                        $s->checkOutIsland($is);
                    };
                    foreach (IslandArchitect::getInstance()->getSessions() as $s) if (
                        ($i = $s->getIsland()) !== null and
                        $i->getName() === $args[1]
                    ) {
                        $path = Utils::cleanPath(IslandArchitect::getInstance()->getConfig()->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/'));
                        $callback($i, $path . ($path[-1] === '/' ? '' : '/') . $i->getName());
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
				if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
				if (isset($args[1])) {
					if (empty(preg_replace('/[0-9]+/i', '', $args[1]))) $regexid = (int)$args[1];
					else foreach ($s->getIsland()->getRandomLabels() as $rid => $label) if (stripos($label, $args[1]) !== false) {
                        $regexid = $rid;
                        break;
                    }
					new InvMenuSession($s, $regexid ?? null);
				} else $s->listRandoms();
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
				$ft = $s->getFloatingText($s::FLOATINGTEXT_SPAWN, true);
				$vec = $vec->floor()->add(0.5, 0.5, 0.5);
				$ft->setComponents($vec->getX(), $vec->getY(), $vec->getZ());
                $ft->setText(TF::BOLD . TF::GOLD . 'Island spawn' . "\n" . TF::RESET . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ());
                $sender->getLevel()->addParticle($ft, [$sender]);
				break;

			case 'setchest':
			case 'chest':
			case 'c':
				if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
				if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
				$vec = $vec ?? $sender->asPosition();
				if (($w = $s->getIsland()->getLevel()) !== null) if ($w !== $vec->getLevel()->getFolderName()) {
					$sender->sendMessage(TF::BOLD . TF::RED . 'You can only run this command in the same world as the island: ' . $w);
					break;
				} else $s->getIsland()->setLevel($vec->getLevel()->getFolderName());
				$sender->sendMessage(TF::YELLOW . 'Island chest position set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setChest($vec);
				break;

            case 'level':
            case 'world':
            case 'l':
                if (PlayerSession::errorCheckOutRequired($sender, $s = IslandArchitect::getInstance()->getSession($sender))) break;
                $is = $s->getIsland();
                if (isset($args[1])) {
                    $dir = scandir(Server::getInstance()->getDataPath() . 'worlds/');
                    unset($dir[array_search('.', $dir, true)]);
                    unset($dir[array_search('..', $dir, true)]);
                    if (!in_array($args[1], $dir, true)) {
                        $sender->sendMessage(TF::BOLD . TF::RED . 'Level does not exists!');
                        break;
                    }
                    $level = $args[1];
                } elseif ($is->getLevel() !== $sender->getLevel()->getFolderName()) $level = $sender->getLevel()->getFolderName();
                else {
                    $sender->sendMessage(TF::BOLD . TF::RED . 'Please enter a valid level name as argument or teleport to another world before running this command!');
                    break;
                }
                $is->setLevel($level);
                $is->setStartCoord(null);
                $is->setEndCoord(null);
                $is->setSpawn(null);
                $is->setChest(null);
                break;

			default:
				$cmds[] = 'help ' . TF::ITALIC . TF::GRAY . '(Display available subcommands)';
                $cmds[] = 'island <Island data file name: string> ' . TF::ITALIC . TF::GRAY . '(Check out or create an island)';
                $cmds[] = 'pos1 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the start coordinate of the island)';
                $cmds[] = 'pos2 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the end coordinate of the island)';
                $cmds[] = 'export ' . TF::ITALIC . TF::GRAY . '(Export the checked out island into template island data file)';
                $cmds[] = 'random [Random regex ID: int] ' . TF::ITALIC . TF::GRAY . '(Setup random blocks generation)';
                $cmds[] = 'setspawn ' . TF::ITALIC . TF::GRAY . '(Set the island world spawn)';
                $cmds[] = 'setchest ' . TF::ITALIC . TF::GRAY . '(Set the island chest position)';
				$sender->sendMessage(TF::BOLD . TF::GOLD . 'Available subcommands: ' . ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $cmds ?? ['help']));
				break;
		}
    }
}