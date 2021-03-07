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

use Clouria\IslandArchitect\{conversion\IslandDataLoadTask,
    events\TemplateIslandCheckOutEvent,
    runtime\sessions\InvMenuSession,
    runtime\sessions\PlayerSession,
    runtime\TemplateIsland};
use pocketmine\{command\Command,
    command\CommandSender,
    level\Position,
    Player,
    Server,
    utils\TextFormat as TF,
    utils\Utils};

class IslandArchitectCommand extends Command {
    // TODO: Customizable class trait

    public function __construct() {
        parent::__construct('island-architect', 'Command of the IslandArchitect plugin', '/island-architect help', ['ia', 'isarch']);
        $this->setPermission('island-architect.cmd');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
        if (!$sender instanceof Player) {
		    $sender->sendMessage(TF::BOLD . TF::RED . 'Please use the command in-game!');
		    return;
        }
		if (
			strtolower($args[1] ?? 'help') !== 'help' and
			!$sender->hasPermission('island-architect.convert')
        ) $sender->sendMessage(Server::getInstance()->getLanguage()->translateString(TF::RED . "%commands.generic.permission"));
		else switch (strtolower($args[0] ?? 'help')) {
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
				} else $s->getIsland()->setLevel($vec->getLevel());
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
				} else $s->getIsland()->setLevel($vec->getLevel());
				$sender->sendMessage(TF::YELLOW . 'End coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setEndCoord($vec);
				break;

			case 'island':
			case 'checkout':
			case 'check-out':
			case 'i':
				if (!isset($args[1])) {
					$sender->sendMessage(TF::BOLD . TF::RED . 'Please enter the island data file name!');
					break;
				}
				$time = microtime(true);
				$sender->sendMessage(TF::YELLOW . 'Loading island ' . TF::GOLD . '"' . $args[1] . '"...');
				$callback = function(?TemplateIsland $is, string $filepath) use ($sender, $time) : void {
					if (!$sender->isOnline()) return;
					if (!isset($is)) $is = new TemplateIsland(basename($filepath, '.json'));
					$s = IslandArchitect::getInstance()->getSession($sender, true);
					$ev = new TemplateIslandCheckOutEvent($s, $is);
					$ev->call();
					if ($ev->isCancelled()) return;
					$s->checkOutIsland($is);
					$sender->sendMessage(TF::BOLD . TF::GREEN . 'Checked out island "' . $is->getName() . '"! ' . TF::ITALIC . TF::GRAY . '(' . round(microtime(true) - $time, 2) . 's)');
				};
				foreach(IslandArchitect::getInstance()->getSessions() as $s) if (
					($i = $s->getIsland()) !== null and
					$i->getName() === $args[1]
				) {
					$path = Utils::cleanPath(IslandArchitect::getInstance()->getConfig()->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/'));
					$callback($i, $path . ($path[-1] === '/' ? '' : '/') . $i->getName());
					break;
				}
				$task = new IslandDataLoadTask($args[1], $callback);
				Server::getInstance()->getAsyncPool()->submitTask($task);
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
				} else $s->getIsland()->setLevel($vec->getLevel());
				$sender->sendMessage(TF::YELLOW . 'Island world spawn set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setSpawn($vec);
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
				} else $s->getIsland()->setLevel($vec->getLevel());
				$sender->sendMessage(TF::YELLOW . 'Island chest position set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$s->getIsland()->setChest($vec);
				break;

			default:
				$cmds[] = 'help ' . TF::ITALIC . TF::GRAY . '(Display available subcommands)';
				if ($sender->hasPermission('island-architect.convert')) {
					$cmds[] = 'island <Island data file name: string> ' . TF::ITALIC . TF::GRAY . '(Check out or create an island)';
					$cmds[] = 'pos1 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the start coordinate of the island)';
					$cmds[] = 'pos2 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the end coordinate of the island)';
					$cmds[] = 'export ' . TF::ITALIC . TF::GRAY . '(Export the checked out island into template island data file)';
					$cmds[] = 'random [Random regex ID: int] ' . TF::ITALIC . TF::GRAY . '(Setup random blocks generation)';
					$cmds[] = 'setspawn ' . TF::ITALIC . TF::GRAY . '(Set the island world spawn)';
					$cmds[] = 'setchest ' . TF::ITALIC . TF::GRAY . '(Set the island chest position)';
				}
				$sender->sendMessage(TF::BOLD . TF::GOLD . 'Available subcommands: ' . ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $cmds ?? ['help']));
				break;
		}
    }
}