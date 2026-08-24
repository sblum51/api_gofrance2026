<?php

namespace App\Security\Voter;

use App\Entity\Parcours;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Parcours>
 */
final class ParcoursVoter extends Voter
{
    public const string VIEW = 'PARCOURS_VIEW';
    public const string EDIT = 'PARCOURS_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Parcours && in_array($attribute, [self::VIEW, self::EDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject instanceof Parcours && $subject->getOrganization()->getOwner()->getId() === $user->getId();
    }
}
