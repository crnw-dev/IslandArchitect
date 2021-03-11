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

use pocketmine\level\Level;

use pocketmine\level\Position;
use room17\SkyBlock\{
    event\island\IslandCreateEvent,
    island\IslandFactory,
    island\RankIds,
    session\Session,
    SkyBlock
};

use Clouria\IslandArchitect\{customized\CustomizableClassTrait,
    events\IslandWorldPreCreateEvent,
    IslandArchitect,
    runtime\TemplateIslandGenerator};

use function is_a;
use function uniqid;
use function assert;
use function microtime;
use function serialize;

class CustomSkyBlockIslandFactory extends IslandFactory {
    use CustomizableClassTrait;

    public static function createIslandWorld(string $identifier, string $type): Level {
        $skyblock = SkyBlock::getInstance();

        $generatorManager = $skyblock->getGeneratorManager();
        if ($generatorManager->isGenerator($type)) $generator = $generatorManager->getGenerator($type);
        elseif (($type = IslandArchitect::getInstance()->mapGeneratorType($type)) !== null) $settings = ['preset' => serialize([$type])];
        else $generator = $generatorManager->getGenerator("Basic");
        $server = $skyblock->getServer();
        $server->generateLevel($identifier, 
null, $generator ?? TemplateIslandGenerator::getClass(), $settings ?? []);
        $server->loadLevel($identifier);
        $level = $server->getLevelByName($identifier);
        if (isset($generator)) $level->setSpawnLocation($generator::getWorldSpawn());

        return $level;
    }

    public static function createIslandFor(Session $session, string $type): void {
        $identifier = uniqid("sb-");
        $islandManager = SkyBlock::getInstance()->getIslandManager();

        $ev = new IslandWorldPreCreateEvent($session, $identifier, $type);
        $ev->call();
        $islandManager->openIsland($identifier, [$session->getOfflineSession()], true, $type,
            $w = self::createIslandWorld($ev->getIdentifier(), $ev->getType()), 0);

        $session->setIsland($island = $islandManager->getIsland($identifier));
        $session->setRank(RankIds::FOUNDER);
        $session->setLastIslandCreationTime(microtime(true));
        $class = TemplateIslandGenerator::getClass();
        assert(is_a($class, TemplateIslandGenerator::class, true));
        $session->getPlayer()->teleport($w->getProvider()->getGenerator() === $class::GENERATOR_NAME ? new Position(0, 0, 0) : $island->getSpawnLocation()); // TODO: Fix god damn coord

        $session->save();
        $island->save();

        (new IslandCreateEvent($island))->call();
    }

}