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
    plugin\Plugin,
    tile\Tile,
    tile\Chest,
    level\Level,
    utils\Utils,
    plugin\PluginBase,
    level\format\Chunk};

use room17\SkyBlock\SkyBlock;
use muqsit\invmenu\InvMenuHandler;
use czechpmdevs\buildertools\BuilderTools;

use Clouria\IslandArchitect\{
    runtime\TemplateIsland,
    events\TickTaskRegisterEvent,
    runtime\sessions\PlayerSession,
    runtime\TemplateIslandGenerator,
    customized\skyblock\CustomSkyBlockCreateCommand,
    worldedit\buildertools\CustomPrinter};

use czechpmdevs\buildertools\editors\Printer;
use function substr;
use function strtolower;
use function file_exists;
use function array_search;
use function class_exists;

class IslandArchitect extends PluginBase {

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

		$class = IslandArchitectCommand::getClass();
		$this->getServer()->getCommandMap()->register($this->getName(), new $class);

		if (SkyBlock::getInstance()->isEnabled()) $this->initDependency(SkyBlock::getInstance());
		if (BuilderTools::getInstance()->isEnabled()) $this->initDependency(BuilderTools::getInstance());

		$task = IslandArchitectPluginTickTask::getClass();
		if (is_a($task, IslandArchitectPluginTickTask::class, true)) $task = new $task;
		$ev = new TickTaskRegisterEvent($task, 10);
		$this->getScheduler()->scheduleRepeatingTask($ev->getTask(), $ev->getPeriod());
	}

    /**
     * @internal
     */
	public function initDependency(Plugin $pl) : void {
	    switch (true) {
            case class_exists(BuilderTools::class) and $pl instanceof BuilderTools:
                $class = CustomPrinter::getClass();
                assert(is_a($class, CustomPrinter::class, true));
                Printer::setInstance(new $class);
                break;

            case class_exists(SkyBlock::class) and $pl instanceof SkyBlock:
                $pl = SkyBlock::getInstance();
                $map = $pl->getCommandMap();
                $cmd = $map->getCommand('create');
                if ($cmd !== null) $pl->getCommandMap()->unregisterCommand($cmd->getName());
                $class = CustomSkyBlockCreateCommand::getClass();
                $map->registerCommand(new $class($map));

                $class = TemplateIslandGenerator::getClass();
                assert(is_a($class, TemplateIslandGenerator::class, true));
                $pl->getGeneratorManager()->registerGenerator($class::GENERATOR_NAME, TemplateIslandGenerator::getClass());
                break;
        }
    }

	private function initConfig() : void {
		$this->saveDefaultConfig();
		$conf = $this->getConfig();
		foreach ($all = $conf->getAll() as $k => $v) $conf->remove($k);

		$conf->set('island-data-folder', Utils::cleanPath((string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/')));
		$conf->set('panel-allow-unstable-item', (bool)($all['panel-allow-unstable-item'] ?? true));
		$conf->set('panel-default-seed', ($pds = $all['panel-default-seed'] ?? null) === null ? null : (int)$pds);
		$conf->set('enable-particles', (bool)($all['enable-particles'] ?? true));
		$conf->set('island-creation-command-mapping', (array)($all['island-creation-command-mapping'] ?? [
		    'generation-name-which-will-be' => 'exported-island-data-file.json',
            'use-in-island-creation-cmd' => 'relative-path/start-from/island-data-folder.json'
        ]));

		$conf->save();
		$conf->reload();
	}

    public function getSession(Player $player, bool $nonnull = false) : ?PlayerSession {
		if (($this->sessions[$player->getName()] ?? null) !== null) $s = $this->sessions[$player->getName()];
		elseif ($nonnull) $s = ($this->sessions[$player->getName()] = new PlayerSession($player));
		return $s ?? null;
	}

	public function mapGeneratorType(string $type) : ?string {
	    foreach ($this->getConfig()->get('island-creation-command-mapping', []) as $st => $file) if (strtolower($st) === strtolower($type)) {
	        $sf = $file;
	        break;
        }
	    if (!isset($sf)) return null;
	    $type = $sf;
	    if (
            !(file_exists($type = Utils::cleanPath($type))) and
            !file_exists($type = Utils::cleanPath(
                (string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/') .
                $type . (strtolower(substr($type, -5)) === '.json' ? '' : '.json')
            ))) $type = null;
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
    private $chestqueue = [];

    public function queueIslandChestCreation(Level $level, TemplateIsland $island) : bool {
        if (($this->chestqueue[$level->getId()] ?? null) !== null) return false;
        $this->chestqueue[$level->getId()] = $island;
        return true;
    }

    public function createIslandChest(Level $level, Chunk $chunk) : bool {
        $is = $this->chestqueue[$level->getId()] ?? null;
        if ($is === null) return false;
        unset($this->chestqueue[$level->getId()]);
        $pos = $is->getChest();
        $pos = $pos->add(0, $is->getYOffset(), 0);
        if ($chunk->getX() !== ($pos->getFloorX() >> 4) or $chunk->getZ() !== ($pos->getFloorZ() >> 4)) return false;

        $chest = Tile::createTile(Tile::CHEST, $level, Chest::createNBT($pos));
        if (!$chest instanceof Chest) return false;
        foreach (SkyBlock::getInstance()->getSettings()->getChestContentByGenerator($is->getName()) as $item) $chest->getInventory()->addItem($item);
        return true;
    }

	public static function getInstance() : ?self {
		return self::$instance;
	}

}
