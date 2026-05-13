<?php

namespace App\Security;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrganizationVoter extends Voter
{
    const VIEW             = 'ORGANIZATION_VIEW';
    const MANAGE_MEMBERS   = 'ORGANIZATION_MANAGE_MEMBERS';
    const OWNER            = 'ORGANIZATION_OWNER';

    public function __construct(
        private readonly OrganizationMemberRepository $memberRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE_MEMBERS, self::OWNER], true)
            && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Organization $organization */
        $organization = $subject;

        return match ($attribute) {
            self::VIEW           => $this->memberRepository->hasRole($user, $organization,
                                       OrganizationRole::OWNER, OrganizationRole::ADMIN, OrganizationRole::MEMBER),
            self::MANAGE_MEMBERS => $this->memberRepository->hasRole($user, $organization,
                                       OrganizationRole::OWNER, OrganizationRole::ADMIN),
            self::OWNER          => $this->memberRepository->hasRole($user, $organization,
                                       OrganizationRole::OWNER),
            default              => false,
        };
    }
}
