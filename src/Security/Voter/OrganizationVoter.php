<?php

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Organization>
 */
final class OrganizationVoter extends Voter
{
    public const string VIEW = 'ORGANIZATION_VIEW';
    public const string EDIT = 'ORGANIZATION_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Organization && in_array($attribute, [self::VIEW, self::EDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject instanceof Organization && $subject->getOwner()->getId() === $user->getId();
    }
}
