<?php

namespace App\Service;

use App\Entity\Organization;

class TenantContext
{
    private ?Organization $currentOrganization = null;

    public function setCurrentOrganization(?Organization $organization): void
    {
        $this->currentOrganization = $organization;
    }

    public function getCurrentOrganization(): ?Organization
    {
        return $this->currentOrganization;
    }

    public function hasOrganization(): bool
    {
        return $this->currentOrganization !== null;
    }
}
