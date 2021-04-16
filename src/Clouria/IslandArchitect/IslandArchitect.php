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

namespace Clouria\IslandArchitect;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\utils\Utils;
use room17\SkyBlock\SkyBlock;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use muqsit\invmenu\InvMenuHandler;
use czechpmdevs\buildertools\BuilderTools;
use czechpmdevs\buildertools\editors\Printer;
use room17\SkyBlock\command\presets\CreateCommand;
use Clouria\IslandArchitect\sessions\PlayerSession;
use Clouria\IslandArchitect\internal\IslandArchitectCommand;
use Clouria\IslandArchitect\generator\TemplateIslandGenerator;
use Clouria\IslandArchitect\extended\buildertools\CustomPrinter;
use Clouria\IslandArchitect\internal\IslandArchitectEventListener;
use Clouria\IslandArchitect\internal\IslandArchitectPluginTickTask;
use Clouria\IslandArchitect\extended\skyblock\CustomSkyBlockCreateCommand;
use function is_a;
use function substr;
use function get_class;
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

    /**
     * @var string
     */
    private $generator_class = TemplateIslandGenerator::class;

    public static function getInstance() : ?self {
        return self::$instance;
    }

    public function onLoad() : void {
        self::$instance = $this;
    }

    public function onEnable() : void {
        $this->initConfig();
        if (class_exists(InvMenuHandler::class)) if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
        $this->getServer()->getPluginManager()->registerEvents(IslandArchitectEventListener::getInstance(), $this);

        $this->getServer()->getCommandMap()->register($this->getName(), new IslandArchitectCommand);

        if (class_exists(SkyBlock::class) and SkyBlock::getInstance()->isEnabled()) $this->initDependency(SkyBlock::getInstance());
        if (class_exists(BuilderTools::class) and BuilderTools::getInstance()->isEnabled()) $this->initDependency(BuilderTools::getInstance());

        $this->getScheduler()->scheduleRepeatingTask(IslandArchitectPluginTickTask::getInstance(), IslandArchitectPluginTickTask::PERIOD);
    }

    private function initConfig() : void {
        $this->saveDefaultConfig();
        $conf = $this->getConfig();
        foreach ($all = $conf->getAll() as $k => $v) $conf->remove($k);

        $conf->set('enable-commands', (bool)($all['enable-commands'] ?? $all['enable-plugin'] ?? true));
        $conf->set('hide-plugin-in-query', (bool)($all['hide-plugin-in-query'] ?? false));
        $conf->set('island-data-folder', (string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/'));
        $conf->set('panel-allow-unstable-item', (bool)($all['panel-allow-unstable-item'] ?? true));
        $conf->set('panel-default-seed', ($pds = $all['panel-default-seed'] ?? null) === null ? null : (int)$pds);
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

    /**
     * @param Plugin $pl
     * @internal
     */
    public function initDependency(Plugin $pl) : void {
        switch (true) {
            case class_exists(BuilderTools::class) and $pl instanceof BuilderTools:
                switch (true) {
                    default:
                        $reflect = new \ReflectionProperty(BuilderTools::class, 'editors');
                        $reflect->setAccessible(true);
                        $editors = $reflect->getValue(BuilderTools::class);
                        /**
                         * @var array<string, \czechpmdevs\buildertools\editors\Editor>
                         */
                        if (
                            isset($editors['Printer']) and
                            get_class($editors['Printer']) !== Printer::class and
                            !$editors['Printer'] instanceof CustomPrinter
                        ) {
                            $this->getLogger()->error('Some plugins do not compatible with IslandArchitect, IslandArchitect cannot re-register the custom printer for BuilderTools');
                            $this->getLogger()->debug('(One of the plugin has already re-registered the printer with a class(' . get_class($editors['Printer']) . ') that does not extends ' . CustomPrinter::class . ')');
                            break;
                        }
                        $editors['Printer'] = new CustomPrinter;
                        $reflect->setValue(BuilderTools::class, $editors);
                        break;
                }
                break;

            case class_exists(SkyBlock::class) and $pl instanceof SkyBlock:
                switch (true) { // So I don't have to do all the if checks again at the register command statement
                    default:
                        $map = $pl->getCommandMap();
                        $cmd = $map->getCommand('create');
                        if ($cmd !== null) {
                            if (get_class($cmd) !== CreateCommand::class and !$cmd instanceof CustomSkyBlockCreateCommand) {
                                $this->getLogger()->error('Some plugins do not compatible with IslandArchitect, IslandArchitect cannot re-register the SkyBlock "create" subcommand!');
                                $this->getLogger()->debug('(One of the plugin has already re-registered the command with a class(' . get_class($cmd) . ') that does not extends ' . CustomSkyBlockCreateCommand::class . ')');
                                break;
                            }
                            $pl->getCommandMap()->unregisterCommand($cmd->getName());
                        }
                        $map->registerCommand(new CustomSkyBlockCreateCommand($map));
                        break;
                }
                switch (true) {
                    default:
                        $class = $pl->getGeneratorManager()->getGenerator($this->getTemplateIslandGenerator()::GENERATOR_NAME);
                        if ($class !== null) {
                            $this->getLogger()->error('Some plugins do not compatible with IslandArchitect, IslandArchitect cannot register the template island generator!');
                            $this->getLogger()
                                 ->debug('(One of the plugin has already registered a generator("' . get_class($class) . '") that does not extends ' . TemplateIslandGenerator::class . ' and uses the same name as template island generator ("' .
                                     $this->getTemplateIslandGenerator()::GENERATOR_NAME . '")' . CustomSkyBlockCreateCommand::class . ')');
                            break;
                        }
                        $pl->getGeneratorManager()->registerGenerator($this->getTemplateIslandGenerator()::GENERATOR_NAME, $this->getTemplateIslandGenerator());
                        break;
                }
                break;
        }
    }

    /**
     * @return class-string<TemplateIslandGenerator>
     */
    public function getTemplateIslandGenerator() {
        if (is_a($this->generator_class, TemplateIslandGenerator::class, true) and is_string($this->generator_class)) return $this->generator_class;
        return TemplateIslandGenerator::class;
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
                ($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/') .
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
     * @param class-string<TemplateIslandGenerator> $class
     */
    public function setTemplateIslandGenerator(string $class) : bool {
        if (!is_a($class, TemplateIslandGenerator::class, true)) return false;
        $this->generator_class = $class;
        return true;
    }

}
