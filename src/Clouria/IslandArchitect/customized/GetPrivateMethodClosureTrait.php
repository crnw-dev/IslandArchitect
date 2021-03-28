<?php


namespace Clouria\IslandArchitect\customized;


trait GetPrivateMethodClosureTrait {

    /**
     * @throws \ReflectionException
     */
    protected function getPrivateMethodClosure(string $method) : \Closure {
        $reflect = new \ReflectionMethod(CreateCommand::class, $method);
        $reflect->setAccessible(true);
        return $reflect->getClosure($this);
    }

}