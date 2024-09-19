<?php

namespace WeCanSync\MultiTenancyBundle\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MultiDbConnectionWrapper extends Connection
{
    

    public function __construct(
        private array $params,
        protected Driver $driver,
        private ?Configuration $config = null,
        private ?EventManager $eventManager = null,
    ) {
        parent::__construct($params, $driver, $config, $eventManager);
    }
    public function changeDatabase(string $dbName)
    {
        
        $params = $this->getParams();
        if ($this->isConnected()) {
            $this->close();
        }
        $mainDb = $params['dbname'];
        $params['dbname'] = $dbName;
        try {
            parent::__construct(
                $params,
                $this->driver,
                $this->config,
                $this->eventManager
            );
            // $this->connect();
            
        } catch (Exception $e) {
            // return to the main db
            $this->changeDatabase($mainDb);
            throw new BadRequestHttpException("Database not yet created.Please try again later.");
        }
    }
}
