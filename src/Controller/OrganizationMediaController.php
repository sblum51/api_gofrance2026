<?php

namespace App\Controller;

use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

final class OrganizationMediaController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/organizations/{id}/logo', name: 'organization_upload_logo', methods: ['POST'])]
    #[IsGranted('ORGANIZATION_EDIT', subject: 'organization')]
    public function logo(Organization $organization, Request $request): JsonResponse
    {
        return $this->upload($organization, $request, static fn (Organization $o, $file) => $o->setLogoFile($file));
    }

    #[Route('/organizations/{id}/cover', name: 'organization_upload_cover', methods: ['POST'])]
    #[IsGranted('ORGANIZATION_EDIT', subject: 'organization')]
    public function cover(Organization $organization, Request $request): JsonResponse
    {
        return $this->upload($organization, $request, static fn (Organization $o, $file) => $o->setCoverImageFile($file));
    }

    private function upload(Organization $organization, Request $request, callable $setter): JsonResponse
    {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException("Aucun fichier reçu (champ 'file' attendu).");
        }

        $setter($organization, $file);
        $this->em->flush();

        $json = $this->serializer->serialize($organization, 'json', ['groups' => ['organization:read']]);

        return JsonResponse::fromJsonString($json);
    }
}
