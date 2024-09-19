<?php

namespace WeCanSync\MultiTenancyBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use WeCanSync\MultiTenancyBundle\DBAL\MultiDbConnectionWrapper;
use WeCanSync\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use WeCanSync\MultiTenancyBundle\Enum\SeedsStatusEnum;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TenantService
{

    private $baseHost;
    private $mainDb;

    public function __construct(
        private EntityManagerInterface $em, 
        private ParameterBagInterface $params, 
        private RequestStack $request,
        private string $tenantEntity
        )
    {
        $this->em = $em;
        $this->params = $params;
        $this->baseHost = $params->get("app.base_host");
        $this->mainDb = $params->get("app.database_name");
        
        if($req = @$this->request->getCurrentRequest()){
            $this->checkCurrentTenant($req);
        }
        
    }

    public function getCurrentTenant(Request $request){

        $connection = $this->em->getConnection();
        if(!$connection instanceof MultiDbConnectionWrapper){
            return null;
        }
        $connection->changeDatabase($this->mainDb);

        $subdomain = $this->findSubdomain($request);

        $tenant = $this->findTenantEntityBySubdomain($subdomain);

        return $tenant;
    }


    public function findSubdomain(Request $request){
        $request->attributes->set('baseHost', $this->baseHost);
        $currentHost = $request->getHttpHost();
        $subdomain = str_replace('.'.$this->baseHost, '', $currentHost);
        if($subdomain == $this->baseHost){
            return null;
        }
        return $subdomain;
    }

    public function checkCurrentTenant(Request $request){
        
        $connection = $this->em->getConnection();
        if(!$connection instanceof MultiDbConnectionWrapper){
            throw new \Exception("Cannot connect to db");
        }
        
        try {
            //$connection->connect();
        } catch (\Exception $e) {
            return;
        }
        
        try{
            $connection->changeDatabase($this->mainDb);
        }catch(Exception $e){
            return;
        }
        
        $subdomain = $this->findSubdomain($request);
        if(!$subdomain){
            return false;
        }
        
        
        $tenant = $this->findTenantEntityBySubdomain($subdomain);
        if(!$tenant){
            throw new BadRequestHttpException("Tenant does not exists.");
        }
        if($tenant->getDbStatus() != DatabaseStatusEnum::DATABASE_CREATED){
            throw new BadRequestHttpException("Database not yet created.Please try again later.");
        }
        if($tenant->getSeedsStatus() != SeedsStatusEnum::SEEDS_CREATED){
            throw new BadRequestHttpException("Database is not configured yet.Please try again later.");
        }
        $database = $tenant->getDbName();
        // switch db connection
        $connection->changeDatabase($database);
    }

    public function findTenantEntityBySubdomain($subdomain){
        return $this->em->getRepository($this->tenantEntity)->findBySubdomain($subdomain);
    }

    public function findTenantEntityByIdentifier($identifier){
        return $this->em->getRepository($this->tenantEntity)->findByIdentifier($identifier);
    }

   

}
