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

use room17\SkyBlock\SkyBlock;
use Clouria\IslandArchitect\{
    customized\skyblock\CustomSkyBlockCreateCommand,
    runtime\sessions\PlayerSession,
    runtime\TemplateIsland,
    runtime\TemplateIslandGenerator};
use muqsit\invmenu\InvMenuHandler;
use pocketmine\{
    level\format\Chunk,
    level\generator\GeneratorManager,
    level\Level,
    Player,
    plugin\PluginBase,
    scheduler\ClosureTask,
    tile\Chest,
    tile\Tile,
    utils\TextFormat as TF,
    utils\Utils};

use function strtolower;
use function file_exists;
use function array_search;
use function class_exists;

class IslandArchitect extends PluginBase {

	public const DEV_ISLAND = false;

	private static $instance = null;

	/**
	 * @var PlayerSession[]
	 */
	private $sessions = [];

    public function onLoad() : void {
		self::$instance = $this;
	}

	public function onEnable() : void {
		$this->initConfig();
		if (class_exists(InvMenuHandler::class)) if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
		$class = EventListener::getClass();
		$this->getServer()->getPluginManager()->registerEvents(new $class, $this);

		$class = TemplateIslandGenerator::getClass();
		if (is_a($class, TemplateIslandGenerator::class, true)) $genname = $class::GENERATOR_NAME;
		else throw new \RuntimeException();
		GeneratorManager::addGenerator($class, $genname, true);

		$class = IslandArchitectCommand::getClass();
		$this->getServer()->getCommandMap()->register($this->getName(), new $class);

		if (SkyBlock::getInstance() !== null and SkyBlock::getInstance()->isEnabled()) $this->initDependency();

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $ct) : void {
		    foreach ($this->getSessions() as $s) if ($s->getIsland() !== null) {
		        $r = $s->getIsland()->getRandomByVector3($s->getPlayer()->getTargetBlock(12));
		        if ($r === null) continue;
		        $s->getPlayer()->sendPopup(TF::YELLOW . 'Random generation block: ' . TF::BOLD . TF::GOLD . $s->getIsland()->getRandomLabel($r));
            }
        }), 10);
	}

    /**
     * @internal
     */
	public function initDependency() : void {
	    $pl = SkyBlock::getInstance();
	    $map = $pl->getCommandMap();
	    $cmd = $map->getCommand('create');
	    if ($cmd !== null) $pl->getCommandMap()->unregisterCommand($cmd->getName());
	    $class = CustomSkyBlockCreateCommand::getClass();
	    $map->registerCommand(new $class($map));
    }

	private function initConfig() : void {
		$this->saveDefaultConfig();
		$conf = $this->getConfig();
		foreach ($all = $conf->getAll() as $k => $v) $conf->remove($k);

		$conf->set('island-data-folder', (string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/'));
		$conf->set('panel-allow-unstable-item', (bool)($all['panel-allow-unstable-item'] ?? true));
		$conf->set('panel-default-seed', ($pds = $all['panel-default-seed'] ?? null) === null ? null : (int)$pds);
		$conf->set('island-creation-command-mapping', (array)($all['island-creation-command-mapping'] ?? [
		    'generation-name-which-will-be' => 'exported-island-data-file.json',
            'use-in-island-creation-cmd' => 'relative-path/start-from/island-data-folder.json'
        ]));

		$conf->save();
		$conf->reload();
	}

    public function getSession(Player $player, bool $nonnull = false) : ?PlayerSession {
		if (self::DEV_ISLAND) $nonnull = true;
		if (($this->sessions[$player->getName()] ?? null) !== null) $s = $this->sessions[$player->getName()];
		elseif ($nonnull) {
			$s = ($this->sessions[$player->getName()] = new PlayerSession($player));
			if (self::DEV_ISLAND) $s->checkOutIsland(new TemplateIsland('test'));
		}
		return $s ?? null;
	}

	public function mapGeneratorType(string $type) : ?string {
	    foreach ($this->getConfig()->get('island-creation-command-mapping', []) as $st => $file) if (strtolower($st) === strtolower($type)) {
	        $sf = $file;
	        break;
        }
	    if (!isset($sf)) return null;
	    $type = $sf;
	    if (isset($type) and !(file_exists($type = Utils::cleanPath($type))) and !file_exists($type = Utils::cleanPath((string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/') . $type))) $type = null;
	    return $type;
    }

    /**
     * @return PlayerSession[]
     */
	public function getSessions() : array {
	    return $this->sessions;
    }

    /**
     * @param PlayerSession $session
     * @return bool Return false if the session has already been disposed or not even in the sessions list
     */
    public function disposeSession(PlayerSession $session) : bool {
        if (($r = array_search($session, $this->sessions, true)) === false) return false;
        if ($this->sessions[$r]->getIsland()) $this->sessions[$r]->saveIsland();
        unset($this->sessions[$r]);
        return true;
    }

    /**
     * @var array<int, TemplateIsland>
     */
    private $chestqueue;

    public function queueIslandChestCreation(Level $level, TemplateIsland $island) : bool {
        if (isset($this->chestqueue[$level->getId()])) return false;
        $this->chestqueue[$level->getId()] = $island;
        return true;
    }

    public function createIslandChest(Level $level, Chunk $chunk) : bool {
        $is = $this->chestqueue[$level->getId()] ?? null;
        if ($is === null) return false;
        unset($this->chestqueue[$level->getId()]);
        $pos = $is->getChest();
        if ($chunk->getX() !== ($pos->getFloorX() >> 4) or $chunk->getZ() !== ($pos->getFloorZ() >> 4)) return false;

        $island = SkyBlock::getInstance()->getIslandManager()->getIsland($level->getName());
        if($island === null) return false;

        $chest = Tile::createTile(Tile::CHEST, $level, Chest::createNBT($pos));
        if (!$chest instanceof Chest) return false;
        foreach (SkyBlock::getInstance()->getSettings()->getChestContentByGenerator($is->getName()) as $item) $chest->getInventory()->addItem($item);
        return true;
    }

	public static function getInstance() : ?self {
		return self::$instance;
	}

}
