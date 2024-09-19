<?php

namespace WeCanSync\MultiTenancyBundle\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\String\Slugger\SluggerInterface;
use WeCanSync\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use WeCanSync\MultiTenancyBundle\Enum\SeedsStatusEnum;
use WeCanSync\MultiTenancyBundle\Enum\MigrationStatusEnum;

/**
 *  Trait to add tenant database configuration to an entity.
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
trait TenantConfigTrait
{
    

    #[ORM\Column(name: "subdomain", type: "string", length: 255, nullable: true)]
    #[Assert\Length(
        min: 3,
        max: 128,
    )]
    public $subdomain;


    #[ORM\Column(name: "dbName", type: "string", length: 255, nullable: true)]
    #[Assert\Length(
        min: 3,
        max: 128,
    )]
    public $dbName;

    #[ORM\Column(name: "dbStatus", type: 'string', length: 255, enumType: DatabaseStatusEnum::class)]
    private DatabaseStatusEnum $dbStatus = DatabaseStatusEnum::DATABASE_NOT_CREATED;

    #[ORM\Column(name: "seedsStatus", type: 'string', length: 255, enumType: SeedsStatusEnum::class)]
    private SeedsStatusEnum $seedsStatus = SeedsStatusEnum::SEEDS_NOT_CREATED;

    #[ORM\Column(name: "migrationStatus", type: 'string', length: 255, enumType: MigrationStatusEnum::class)]
    private MigrationStatusEnum $migrationStatus = MigrationStatusEnum::MIGRATION_NOT_CREATED;

    
    public function getSubdomain(): ?string
    {
        return $this->subdomain;
    }

    public function setSubdomain(?string $subdomain): static
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    public function setDbName(?string $dbName): static
    {
        $this->dbName = $dbName;

        return $this;
    }

    public function getDbStatus(): ?DatabaseStatusEnum
    {
        return $this->dbStatus;
    }

    public function setDbStatus(DatabaseStatusEnum $dbStatus): static
    {
        $this->dbStatus = $dbStatus;

        return $this;
    }

    

    public function generateDatabase($name){
        $this->dbName = $name . "_tenant_" . $this->getId();
    }

    public function getSeedsStatus(): ?SeedsStatusEnum
    {
        return $this->seedsStatus;
    }

    public function setSeedsStatus(SeedsStatusEnum $seedsStatus): static
    {
        $this->seedsStatus = $seedsStatus;

        return $this;
    }

    public function getMigrationStatus(): ?MigrationStatusEnum
    {
        return $this->migrationStatus;
    }

    public function setMigrationStatus(MigrationStatusEnum $migrationStatus): static
    {
        $this->migrationStatus = $migrationStatus;

        return $this;
    }
}
