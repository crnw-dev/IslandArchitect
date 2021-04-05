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
use muqsit\invmenu\InvMenuHandler;
use czechpmdevs\buildertools\BuilderTools;
use pocketmine\{
    Player,
    item\Item,
    tile\Tile,
    tile\Chest,
    level\Level,
    utils\Utils,
    plugin\Plugin,
    plugin\PluginBase,
    level\format\Chunk};
use Clouria\IslandArchitect\{
    runtime\TemplateIsland,
    events\TickTaskRegisterEvent,
    runtime\sessions\PlayerSession,
    runtime\TemplateIslandGenerator,
    worldedit\buildertools\CustomPrinter,
    customized\skyblock\CustomSkyBlockCreateCommand};
use function substr;
use function strtolower;
use function file_exists;
use function array_search;
use function class_exists;

class IslandArchitect extends PluginBase {

    public const DEFAULT_REGEX = [
        'Random ores' => [
            Item::STONE . ':0' => 24,
            Item::IRON_ORE . ':0' => 12,
            Item::GOLD_ORE . ':0' => 2,
            Item::DIAMOND_ORE . ':0' => 1,
            Item::LAPIS_ORE . ':0' => 2,
            Item::REDSTONE_ORE . ':0' => 4,
            Item::COAL_ORE . ':0' => 12,
            Item::EMERALD_ORE . ':0' => 1
        ],
        'Random chest directions' => [
            Item::CHEST . ':0' => 1,
            Item::CHEST . ':2' => 1,
            Item::CHEST . ':4' => 1,
            Item::CHEST . ':5' => 1
        ],
        'Random flowers' => [
            // This does not follow generation rate of bone-mealing grass in PocketMine-MP
            Item::TALLGRASS . ':1' => 4,
            Item::AIR . ':0' => 8,
            Item::YELLOW_FLOWER . ':0' => 1,
            Item::RED_FLOWER . ':0' => 1
        ],
        'Random wool colours' => [
            Item::WOOL . ':0' => 1,
            Item::WOOL . ':1' => 1,
            Item::WOOL . ':2' => 1,
            Item::WOOL . ':3' => 1,
            Item::WOOL . ':4' => 1,
            Item::WOOL . ':5' => 1,
            Item::WOOL . ':6' => 1,
            Item::WOOL . ':7' => 1,
            Item::WOOL . ':8' => 1,
            Item::WOOL . ':9' => 1,
            Item::WOOL . ':10' => 1,
            Item::WOOL . ':11' => 1,
            Item::WOOL . ':12' => 1,
            Item::WOOL . ':13' => 1,
            Item::WOOL . ':14' => 1,
            Item::WOOL . ':15' => 1,
        ],
        'Random terracotta colours' => [
            Item::TERRACOTTA . ':0' => 1,
            Item::TERRACOTTA . ':1' => 1,
            Item::TERRACOTTA . ':2' => 1,
            Item::TERRACOTTA . ':3' => 1,
            Item::TERRACOTTA . ':4' => 1,
            Item::TERRACOTTA . ':5' => 1,
            Item::TERRACOTTA . ':6' => 1,
            Item::TERRACOTTA . ':7' => 1,
            Item::TERRACOTTA . ':8' => 1,
            Item::TERRACOTTA . ':9' => 1,
            Item::TERRACOTTA . ':10' => 1,
            Item::TERRACOTTA . ':11' => 1,
            Item::TERRACOTTA . ':12' => 1,
            Item::TERRACOTTA . ':13' => 1,
            Item::TERRACOTTA . ':14' => 1,
            Item::TERRACOTTA . ':15' => 1,
        ],
        'Random concrete colours' => [
            Item::CONCRETE . ':0' => 1,
            Item::CONCRETE . ':1' => 1,
            Item::CONCRETE . ':2' => 1,
            Item::CONCRETE . ':3' => 1,
            Item::CONCRETE . ':4' => 1,
            Item::CONCRETE . ':5' => 1,
            Item::CONCRETE . ':6' => 1,
            Item::CONCRETE . ':7' => 1,
            Item::CONCRETE . ':8' => 1,
            Item::CONCRETE . ':9' => 1,
            Item::CONCRETE . ':10' => 1,
            Item::CONCRETE . ':11' => 1,
            Item::CONCRETE . ':12' => 1,
            Item::CONCRETE . ':13' => 1,
            Item::CONCRETE . ':14' => 1,
            Item::CONCRETE . ':15' => 1,
        ],
        'Random concrete powder colours' => [
            Item::CONCRETE_POWDER . ':0' => 1,
            Item::CONCRETE_POWDER . ':1' => 1,
            Item::CONCRETE_POWDER . ':2' => 1,
            Item::CONCRETE_POWDER . ':3' => 1,
            Item::CONCRETE_POWDER . ':4' => 1,
            Item::CONCRETE_POWDER . ':5' => 1,
            Item::CONCRETE_POWDER . ':6' => 1,
            Item::CONCRETE_POWDER . ':7' => 1,
            Item::CONCRETE_POWDER . ':8' => 1,
            Item::CONCRETE_POWDER . ':9' => 1,
            Item::CONCRETE_POWDER . ':10' => 1,
            Item::CONCRETE_POWDER . ':11' => 1,
            Item::CONCRETE_POWDER . ':12' => 1,
            Item::CONCRETE_POWDER . ':13' => 1,
            Item::CONCRETE_POWDER . ':14' => 1,
            Item::CONCRETE_POWDER . ':15' => 1,
        ],
        'Random glazed terracotta colours' => [
            Item::PURPLE_GLAZED_TERRACOTTA . ':0' => 1,
            Item::WHITE_GLAZED_TERRACOTTA . ':0' => 1,
            Item::ORANGE_GLAZED_TERRACOTTA . ':0' => 1,
            Item::MAGENTA_GLAZED_TERRACOTTA . ':0' => 1,
            Item::LIGHT_BLUE_GLAZED_TERRACOTTA . ':0' => 1,
            Item::YELLOW_GLAZED_TERRACOTTA . ':0' => 1,
            Item::LIME_GLAZED_TERRACOTTA . ':0' => 1,
            Item::PINK_GLAZED_TERRACOTTA . ':0' => 1,
            Item::GRAY_GLAZED_TERRACOTTA . ':0' => 1,
            Item::SILVER_GLAZED_TERRACOTTA . ':0' => 1,
            Item::CYAN_GLAZED_TERRACOTTA . ':0' => 1,
            Item::BLUE_GLAZED_TERRACOTTA . ':0' => 1,
            Item::BROWN_GLAZED_TERRACOTTA . ':0' => 1,
            Item::GREEN_GLAZED_TERRACOTTA . ':0' => 1,
            Item::RED_GLAZED_TERRACOTTA . ':0' => 1,
            Item::BLACK_GLAZED_TERRACOTTA . ':0' => 1
        ]
    ];

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

		if (class_exists(SkyBlock::class) and SkyBlock::getInstance()->isEnabled()) $this->initDependency(SkyBlock::getInstance());
		if (class_exists(BuilderTools::class) and BuilderTools::getInstance()->isEnabled()) $this->initDependency(BuilderTools::getInstance());

		$task = IslandArchitectPluginTickTask::getClass();
		if (is_a($task, IslandArchitectPluginTickTask::class, true)) $task = new $task;
		$ev = new TickTaskRegisterEvent($task, 10);
		$this->getScheduler()->scheduleRepeatingTask($ev->getTask(), $ev->getPeriod());
	}

    /**
     * @param Plugin $pl
     * @internal
     */
	public function initDependency(Plugin $pl) : void {
	    switch (true) {
            case class_exists(BuilderTools::class) and $pl instanceof BuilderTools:
                $class = CustomPrinter::getClass();
                assert(is_a($class, CustomPrinter::class, true));

                $reflect = new \ReflectionProperty(BuilderTools::class, 'editors');
                $reflect->setAccessible(true);
                $editors = $reflect->getValue(BuilderTools::class);
                $editors['Printer'] = new $class;
                $reflect->setValue(BuilderTools::class, $editors);
                break;

            case class_exists(SkyBlock::class) and $pl instanceof SkyBlock:
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
		$conf->set('panel-default-seed', ($pds = $all['panel-default-seed'] ?? null) === null ? null : (int)$pds);
		$conf->set('island-creation-command-mapping', (array)($all['island-creation-command-mapping'] ?? [
		    'generation-name-which-will-be' => 'exported-island-data-file.json',
            'use-in-island-creation-cmd' => 'relative-path/start-from/island-data-folder.json'
        ]));
		$conf->set('default-regex', (array)($all['default-regex'] ?? self::DEFAULT_REGEX));

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
        $pos = $pos->add(0, $is->getYOffset());
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
