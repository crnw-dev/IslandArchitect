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

use Clouria\IslandArchitect\{
    IslandArchitect,
    customized\GetPrivateMethodClosureTrait
};
use room17\SkyBlock\{
    SkyBlock,
    session\Session,
    command\presets\CreateCommand,
    utils\message\MessageContainer
};
use function strtolower;

class CustomSkyBlockCreateCommand extends CreateCommand {
    use GetPrivateMethodClosureTrait;

    /**
     * @throws \ReflectionException
     */
    public function onCommand(Session $session, array $args) : void {
        if ($this->getPrivateMethodClosure('checkIslandAvailability')($session) or $this->getPrivateMethodClosure('checkIslandCreationCooldown')($session)) return;

        $generator = strtolower($args[0] ?? "Shelly");
        if ((SkyBlock::getInstance()->getGeneratorManager()->isGenerator($generator) or IslandArchitect::getInstance()->mapGeneratorType($generator) !== null) and $this->getPrivateMethodClosure('hasPermission')($session, $generator)) {
            CustomSkyBlockIslandFactory::createIslandFor($session, $generator); // TODO: Deprecate the customized island factor class
            $session->sendTranslatedMessage(new MessageContainer("SUCCESSFULLY_CREATED_A_ISLAND"));
        } else $session->sendTranslatedMessage(new MessageContainer("NOT_VALID_GENERATOR", ["name" => $generator]));
    }

}