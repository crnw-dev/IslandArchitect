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

namespace Clouria\IslandArchitect\extended\skyblock;

use pocketmine\Server;
use pocketmine\level\Level;
use room17\SkyBlock\SkyBlock;
use pocketmine\level\Position;
use room17\SkyBlock\island\RankIds;
use room17\SkyBlock\session\Session;
use room17\SkyBlock\island\IslandFactory;
use Clouria\IslandArchitect\IslandArchitect;
use room17\SkyBlock\command\presets\CreateCommand;
use room17\SkyBlock\event\island\IslandCreateEvent;
use room17\SkyBlock\utils\message\MessageContainer;
use Clouria\IslandArchitect\generator\TemplateIsland;
use Clouria\IslandArchitect\events\IslandWorldPreCreateEvent;
use Clouria\IslandArchitect\generator\tasks\IslandDataLoadTask;
use function strtolower;

class CustomSkyBlockCreateCommand extends CreateCommand {

    public function onCommand(Session $session, array $args) : void {
        if ($this->checkIslandAvailability($session) or $this->checkIslandCreationCooldown($session)) return;

        $generator = strtolower($args[0] ?? "Shelly");
        if ((SkyBlock::getInstance()->getGeneratorManager()->isGenerator($generator) or IslandArchitect::getInstance()->mapGeneratorType($generator) !== null) and $this->hasPermission($session, $generator)) {
            static::createIslandFor($session, $generator);
            $session->sendTranslatedMessage(new MessageContainer("SUCCESSFULLY_CREATED_A_ISLAND"));
        } else $session->sendTranslatedMessage(new MessageContainer("NOT_VALID_GENERATOR", ["name" => $generator]));
    }

    private function checkIslandAvailability(Session $session) : bool {
        $hasIsland = $session->hasIsland();
        if ($hasIsland) {
            $session->sendTranslatedMessage(new MessageContainer("NEED_TO_BE_FREE"));
        }
        return $hasIsland;
    }

    private function checkIslandCreationCooldown(Session $session) : bool {
        $minutesSinceLastIsland =
            $session->hasLastIslandCreationTime()
                ? (microtime(true) - $session->getLastIslandCreationTime()) / 60
                : -1;
        $cooldownDuration = SkyBlock::getInstance()->getSettings()->getCreationCooldownDuration();
        if ($minutesSinceLastIsland !== -1 and $minutesSinceLastIsland < $cooldownDuration) {
            $session->sendTranslatedMessage(new MessageContainer("YOU_HAVE_TO_WAIT", [
                "minutes" => ceil($cooldownDuration - $minutesSinceLastIsland),
            ]));
            return true;
        }
        return false;
    }

    private function hasPermission(Session $session, string $generator) : bool {
        return $session->getPlayer()->hasPermission("skyblock.island.$generator");
    }

    public static function createIslandFor(Session $session, string $type) : void {
        $mapped = IslandArchitect::getInstance()->mapGeneratorType($type);
        $ev = new IslandWorldPreCreateEvent($session, isset($mapped) ? static::createIslandIdentifier() : null, $mapped ?? $type, isset($mapped));
        $ev->call();
        if ($mapped === null and !SkyBlock::getInstance()->getGeneratorManager()->isGenerator($ev->getType())) return;
        $identifier = $ev->getIdentifier();
        if ($mapped === null and $identifier === null) {
            IslandFactory::createIslandFor($session, $ev->getType());
            return;
        }

        $type = $ev->getType();
        $islandManager = SkyBlock::getInstance()->getIslandManager();
        $callback = function(Level $w) use ($session, $islandManager, $identifier) : void {
            if (!$session->getPlayer()->isOnline()) return;
            $islandManager->openIsland($identifier, [$session->getOfflineSession()], true, IslandArchitect::getInstance()->getTemplateIslandGenerator()::GENERATOR_NAME,
                $w, 0);

            $session->setIsland($island = $islandManager->getIsland($identifier));
            $session->setRank(RankIds::FOUNDER);
            $session->setLastIslandCreationTime(microtime(true));
            $session->getPlayer()->teleport($island->getSpawnLocation());

            $session->save();
            $island->save();

            (new IslandCreateEvent($island))->call();
        };

        if ($mapped === null) {
            $generatorManager = SkyBlock::getInstance()->getGeneratorManager();
            if ($generatorManager->isGenerator($type)) {
                $generator = $generatorManager->getGenerator($type);
            } else {
                $generator = $generatorManager->getGenerator("Basic");
            }

            $server = SkyBlock::getInstance()->getServer();
            $server->generateLevel($identifier, null, $generator);
            $server->loadLevel($identifier);
            $level = $server->getLevelByName($identifier);
            $level->setSpawnLocation($generator::getWorldSpawn());

            $callback($level);
        } else static::createTemplateIslandWorldAsync($identifier, $type, $callback);
    }

    protected static function createIslandIdentifier() : string {
        return uniqid("sb-");
    }

    /**
     * @param string $identifier
     * @param string $type
     * @param \Closure $callback Should compatible with <code>function(<@link Level> $level) {}</code>
     */
    public static function createTemplateIslandWorldAsync(string $identifier, string $type, \Closure $callback) : void {
        $task = new IslandDataLoadTask($type, function(TemplateIsland $is, string $file) use ($identifier, $callback) : void {
            $settings = ['preset' => serialize([$is->exportRaw()])];
            Server::getInstance()->generateLevel($identifier,
                null, IslandArchitect::getInstance()->getTemplateIslandGenerator(), $settings ?? []);
            Server::getInstance()->loadLevel($identifier);
            $level = Server::getInstance()->getLevelByName($identifier);

            if ($is->getSpawn() !== null) {
                $spawn = $is->getSpawn();
                $spawn->setComponents($spawn->getX(), $spawn->getY() + $is->getYOffset(), $spawn->getZ());
            } else $spawn = new Position(0, 0, 0, $level);
            $level->setSpawnLocation($spawn);
            $callback($level);
        });
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

}