<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des accès à une organisation. Tout membre peut le faire : il n'y a
 * pas de hiérarchie entre gestionnaires.
 */
final class OrganizationMemberController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly MembershipRepository $memberships,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/organizations/{id}/members', name: 'organization_members', methods: ['GET'])]
    #[IsGranted('ORGANIZATION_VIEW', 'organization')]
    public function list(Organization $organization): JsonResponse
    {
        return new JsonResponse(['members' => $this->serialize($organization)]);
    }

    #[Route('/api/organizations/{id}/members', name: 'organization_member_add', methods: ['POST'])]
    #[IsGranted('ORGANIZATION_EDIT', 'organization')]
    public function add(Organization $organization, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
        if ('' === $email) {
            throw new BadRequestHttpException("Indiquez l'adresse du gestionnaire à ajouter.");
        }

        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            // Pas d'invitation par jeton pour l'instant : la personne crée son
            // compte, puis on l'ajoute. Message explicite plutôt qu'un échec
            // muet, sinon on ne comprend pas pourquoi l'ajout ne marche pas.
            throw new NotFoundHttpException(
                "Aucun compte avec cette adresse. Demandez à la personne de créer son compte sur le manager, puis ajoutez-la.",
            );
        }

        if (null !== $this->memberships->findOneFor($user, $organization)) {
            throw new ConflictHttpException('Cette personne gère déjà cette organisation.');
        }

        $this->entityManager->persist(
            (new Membership())->setUser($user)->setOrganization($organization),
        );
        $this->entityManager->flush();

        return new JsonResponse(['members' => $this->serialize($organization)], 201);
    }

    #[Route('/api/organizations/{id}/members/{membershipId}', name: 'organization_member_remove', methods: ['DELETE'])]
    #[IsGranted('ORGANIZATION_EDIT', 'organization')]
    public function remove(Organization $organization, string $membershipId): JsonResponse
    {
        $membership = $this->memberships->find($membershipId);
        if (null === $membership || $membership->getOrganization()->getId() !== $organization->getId()) {
            throw new NotFoundHttpException('Accès introuvable.');
        }

        // Deux garde-fous : on ne se retire pas soi-même par mégarde, et on ne
        // laisse jamais une organisation sans aucun gestionnaire — plus
        // personne ne pourrait alors y accéder.
        $courant = $this->security->getUser();
        if ($courant instanceof UserInterface
            && $membership->getUser()->getUserIdentifier() === $courant->getUserIdentifier()) {
            throw new BadRequestHttpException('Vous ne pouvez pas retirer votre propre accès.');
        }

        if ($organization->getMemberships()->count() <= 1) {
            throw new BadRequestHttpException(
                "C'est le dernier gestionnaire : l'organisation deviendrait ingérable.",
            );
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return new JsonResponse(['members' => $this->serialize($organization)]);
    }

    /** @return list<array<string, string>> */
    private function serialize(Organization $organization): array
    {
        $membres = array_map(
            static fn (Membership $m): array => [
                'id' => $m->getId(),
                'email' => $m->getEmail(),
            ],
            $organization->getMemberships()->toArray(),
        );
        usort($membres, static fn (array $a, array $b): int => $a['email'] <=> $b['email']);

        return array_values($membres);
    }
}
