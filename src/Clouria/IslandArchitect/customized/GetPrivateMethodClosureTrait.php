<?php


namespace Clouria\IslandArchitect\customized;


trait GetPrivateMethodClosureTrait {

    /**
     * @param string $method
     * @return \Closure
     * @throws \ReflectionException
     */
    protected function getPrivateMethodClosure(string $method) : \Closure {
        $reflect = new \ReflectionMethod(self::class, $method);
        $reflect->setAccessible(true);
        return $reflect->getClosure($this);
    }

}