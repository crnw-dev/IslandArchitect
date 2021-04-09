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

namespace Clouria\IslandArchitect\customized\skyblock;

use pocketmine\{
    Server,
    level\Level,
    level\Position};
use room17\SkyBlock\{
    SkyBlock,
    island\RankIds,
    session\Session,
    island\IslandFactory,
    event\island\IslandCreateEvent
};
use Clouria\IslandArchitect\{
    ApiMap,
    IslandArchitect,
    runtime\TemplateIsland,
    conversion\IslandDataLoadTask,
    events\IslandWorldPreCreateEvent
};
use function uniqid;
use function microtime;
use function serialize;

class CustomSkyBlockIslandFactory extends IslandFactory {

    public static function createIslandFor(Session $session, string $type): void {
        $mapped = IslandArchitect::getInstance()->mapGeneratorType($type);
        if ($mapped === null) {
            parent::createIslandFor($session, $type);
            return;
        }

        $ev = new IslandWorldPreCreateEvent($session, static::createIslandIdentifier(), $mapped);
        $ev->call();
        $identifier = $ev->getIdentifier();
        $type = $ev->getType();
        $islandManager = SkyBlock::getInstance()->getIslandManager();

        static::createTemplateIslandWorldAsync($identifier, $type, function(Level $w) use
        ($session, $islandManager, $identifier) : void {
            if (!$session->getPlayer()->isOnline()) return;
            $islandManager->openIsland($identifier, [$session->getOfflineSession()], true, ApiMap::getInstance()->getTemplateIslandGeneratorClass()::GENERATOR_NAME,
                $w, 0);

            $session->setIsland($island = $islandManager->getIsland($identifier));
            $session->setRank(RankIds::FOUNDER);
            $session->setLastIslandCreationTime(microtime(true));
            $session->getPlayer()->teleport($island->getSpawnLocation());

            $session->save();
            $island->save();

            (new IslandCreateEvent($island))->call();
        });
    }

    /**
     * @param string $identifier
     * @param string $type
     * @param \Closure $callback Should compatible with <code>function(<@link Level> $level) {}</code>
     */
    public static function createTemplateIslandWorldAsync(string $identifier, string $type, \Closure $callback) : void {
        $task = new IslandDataLoadTask($type, function(TemplateIsland $is, string $file) use
        ($identifier, $callback) : void {
            $settings = ['preset' => serialize([$is->exportRaw()])];
            Server::getInstance()->generateLevel($identifier,
                null, ApiMap::getInstance()->getTemplateIslandGeneratorClass()::class, $settings ?? []);
            Server::getInstance()->loadLevel($identifier);
            $level = Server::getInstance()->getLevelByName($identifier);

            if ($is->getSpawn() !== null) {
                $spawn = $is->getSpawn();
                $spawn->setComponents($spawn->getX(), $spawn->getY() + $is->getYOffset(), $spawn->getZ());
            } else $spawn = new Position(0, 0,0, $level);
            $level->setSpawnLocation($spawn);
            $callback($level);
        });
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    protected static function createIslandIdentifier() : string {
        return uniqid("sb-");
    }

}