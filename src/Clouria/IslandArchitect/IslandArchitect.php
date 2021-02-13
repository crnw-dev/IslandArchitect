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
namespace Clouria\IslandArchitect;

use pocketmine\{
	Player,
	plugin\PluginBase,
	command\Command,
	command\CommandSender,
	command\PluginCommand,
	utils\TextFormat as TF,
	level\Position,
	scheduler\ClosureTask
};
use pocketmine\event\{
	Listener,
	player\PlayerInteractEvent,
	block\BlockPlaceEvent,
	block\BlockBreakEvent
};

use muqsit\invmenu\InvMenuHandler;

use Clouria\IslandArchitect\{
	conversion\ConvertSession,
	api\IslandAttributeTile,
	api\RandomGeneration
};

use function strtolower;
use function implode;
use function count;
use function class_exists;

class IslandArchitect extends PluginBase implements Listener {

	private static $instance = null;

	/**
	 * @var ConvertSession[]
	 */
	private $sessions = [];

	public function onLoad() : void {
		self::$instance = $this;
	}

	public function onEnable() : void {
		if (!$this->initConfig()) {
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		IslandAttributeTile::registerTile(IslandAttributeTile::class, ['IslandAttributeTile']);
		if (class_exists(InvMenuHandler::class)) if(!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->registerCommands();
	}

	private function initConfig() : bool {
		$this->saveDefaultConfig();
		$conf = $this->getConfig();
		foreach ($all = $conf->getAll() as $k => $v) $conf->remove($k);

		$conf->set('enable-plugin', (bool)($all['enable-plugin'] ?? true));
		$conf->set('island-data-folder', (bool)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/'));

		return (bool)$conf->get('enable-plugin', true);
	}

	public function registerCommands() : void {
		$cmd = new PluginCommand('island-architect', $this);
		$cmd->setDescription('Command of the IslandArchitect plugin');
		$cmd->setUsage('/island-architect help');
		$cmd->setAliases(['ia', 'isarch']);
		$cmd->setPermission('island-architect.cmd');
		$this->getServer()->getCommandMap()->register($this->getName(), $cmd);
	}

	public function getSession(Player $player) : ConvertSession {
		$session = $this->sessions[$player->getName()] ?? ($this->sessions[$player->getName()] = new ConvertSession($player));
		$session->updatePlayer($player);
		return $session;
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $alias, array $args) : bool {
		if ((!$sender instanceof Player) and strtolower($args[0]) !== 'reset-all') $sender->sendMessage(TF::BOLD . TF::RED . 'Please use the command in-game!');
		else switch (strtolower($args[0] ?? 'help')) {
			case 'reset':
				if (isset($args[1])) {
					if (!$sender->hasPermission('island-architect.reset-all')) return false;
					$sp = $this->getServer()->getPlayer($args[1]);
					if (!isset($sp)) {
						$sender->sendMessage(TF::BOLD . TF::RED . 'Player not found!');
						break;
					}
				} else $sp = $sender;
				if (!isset($this->sessions[$sp->getName()])) break;
				if (!$this->sessions[$sp->getName()]->isIdle()) if (!(bool)($args[2] ?? false)) {
					$sender->sendMessage(TF::BOLD . TF::RED . 'This convert session is not in idle!');
				}
				unset($this->sessions[$sp->getName()]);
				$sp->sendMessage(TF::BOLD . TF::GOLD . 'Your convert session has been ' . TF::RED . 'reset!');
				break;

			case 'reset-all':
				if (!$sender->hasPermission('island-architect.reset-all')) return false;
				foreach ($this->sessions as $i => $s) if ($s->isIdle()) {
					unset($this->sessions[$i]);
					$destructed++;
				}
				$sender->sendMessage(TF::BOLD . TF::YELLOW . 'Destructed ' . TF::GREEN . ($destructed ?? 0) . TF::YELLOW . ' convert session instances!');
				break;

			case 'pos1':
			case 'p1':
			case '1':
				if (!$sender->hasPermission('island-architect.convert')) return false;
				if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
				$vec = $vec ?? $sender->asPosition();
				$sender->sendMessage(TF::YELLOW . 'Start coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$this->getSession($sender)->startCoord($vec);
				break;

			case 'pos2':
			case 'p2':
			case '2':
				if (!$sender->hasPermission('island-architect.convert')) return false;
				if (isset($args[1]) and isset($args[2]) and isset($args[3])) $vec = new Position((int)$args[1], (int)$args[2], (int)$args[3], $sender->getLevel());
				$vec = $vec ?? $sender->asPosition();
				$sender->sendMessage(TF::YELLOW . 'End coordinate set to ' . TF::GREEN . $vec->getFloorX() . ', ' . $vec->getFloorY() . ', ' . $vec->getFloorZ() . '.');
				$this->getSession($sender)->endCoord($vec);
				break;

			case 'random':
				if (isset($args[1])) $args[1] = (int)$args[1];
				$this->getSession($sender)->editRandom($args[1] ?? null);
				break;
		
			default:
				$cmds[] = 'help ' . TF::ITALIC . TF::GRAY . '(Display available subcommands)';
				if ($sender->hasPermission('island-architect.convert')) {
					$cmds[] = 'pos1 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the start coordinate of the island for convert)';
					$cmds[] = 'pos2 [xyz: int] ' . TF::ITALIC . TF::GRAY . '(Set the end coordinate of the island for convert)';
					$cmds[] = 'convert ' . TF::ITALIC . TF::GRAY . '(Convert the selected island area to JSON island template file)';
					$cmds[] = 'random [Random function ID: int] ' . TF::ITALIC . TF::GRAY . '(Setup random blocks generation)';
					if ($sender->hasPermission('island-architect.reset-all')) $cmds[] = 'reset [Player: string] [Forced: bool]' . TF::ITALIC . TF::GRAY . '(Reset someone\'s convert session instance)';
					else $cmds[] = 'reset ' . TF::ITALIC . TF::GRAY . '(Reset your convert session instance)';
				}
				if ($sender->hasPermission('island-architect.reset-all')) $cmds[] = 'reset-all ' . TF::ITALIC . TF::GRAY . '(Destruct all the convert session instances for saving up server memory)';
				$sender->sendMessage(TF::BOLD . TF::GOLD . 'Available subcommands: ' . ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $cmds ?? ['help']));
				break;
		}
		return true;
	}

	/**
	 * @ignoreCancelled
	 */
	public function onPlayerInteract(PlayerInteractEvent $ev) : void {
		foreach ($this->sessions as $s) $s->onPlayerInteract($ev);
	}

	/**
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $ev) : void {
		foreach ($this->sessions as $s) $s->onBlockPlace($ev);
	}

	/**
	 * @ignoreCancelled
	 */
	public function onBlockBreak(BlockBreakEvent $ev) : void {
		if (!($tile = $ev->getBlock()->getLevel()->getTile($ev->getBlock()->asVector3())) instanceof IslandAttributeTile) return;
		$ev->getPlayer()->sendPopup(TF::BOLD . TF::YELLOW . 'You have destroyed a random generation block');
		foreach ($ev->getPlayer()->getInventory()->getContents() as $item) {
			$add = false;
			if (($nbt = $item->getNamedTagEntry('IslandArchitect')) == null) $add = true;
			if (($nbt = $nbt->getCompoundTag('random-generation')) === null) $add = true;
			if (($nbt = $nbt->getListTag('regex')) === null) $add = true;
			if (RandomGeneration::fromNBT($nbt)->equals($regex = $tile->getRandomGeneration())) $add = false;
			if ($add) ConvertSession::giveRandomGenerationBlock($ev->getPlayer(), $regex, false);
		}
	}

	public static function getInstance() : ?self {
		return self::$instance;
	}
	
}
