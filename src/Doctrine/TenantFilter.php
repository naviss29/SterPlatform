<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('organizationId') && !$targetEntity->hasAssociation('organization')) {
            return '';
        }

        $orgId = $this->getParameter('organization_id');

        if ($targetEntity->hasAssociation('organization')) {
            return sprintf('%s.organization_id = %s', $targetTableAlias, $orgId);
        }

        return '';
    }
}
