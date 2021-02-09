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
	plugin\PluginBase,
	command\Command,
	command\CommandSender,
	utils\TextFormat
};
use pocketmine\event\{
	Listener
};

use function strtolower;

class IslandArchitect extends PluginBase implements Listener, API {

	private static $instance = null;

	public function onLoad() : void {
		self::$instance = $this;
	}

	public function onEnable() : void {
		if (!$this->initConfig()) {
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
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

	public function onCommand(CommandSender $sender, Command $cmd, string $alias, array $args) : bool {
		switch (strtolower($cmd->getName())) {
			default:
				$cmds[] = 'help ' . TF::ITALIC . TF::GRAY . '(Display available subcommands)';
				if ($sender->hasPermission('island-architect.convert')) {
					$cmds[] = 'pos1 [xyz: int]' . TF::ITALIC . TF::GRAY . '(Set the start coordinate of the island for convert)';
					$cmds[] = 'pos2 [xyz: int]' . TF::ITALIC . TF::GRAY . '(Set the end coordinate of the island for convert)';
					$cmds[] = 'convert [First coord xyz: int] [Second coord xyz: int]' . TF::ITALIC . TF::GRAY . '(Convert the selected island area to JSON island template file)';
					$cmds[] = 'reset ' . TF::ITALIC . TF::GRAY . '(Reset island start, end coordinates and attributes)';
				}
				if ($sender->hasPermission('island-architect.tct')) $cmds[] = 'attrib [Block xyz: int] <(f)unction|(r)andom|(l)ist> [Function name: string|Random regex: string]' . TF::ITALIC . TF::GRAY . '(Modify island attributes)';
				if ($sender->hasPermission('island-architect.tct.function')) $cmds[] = 'function <(a)dd|(m)odify|(d)el|(l)ist> [Type: string/int] [Parameters: string]' . TF::ITALIC . TF::GRAY . '(Modify island attributes)';
				break;
		}
	}

	public static function getInstance() : ?self {
		return self::$instance;
	}
	
}
