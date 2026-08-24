<?php

namespace App\Controller;

use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

final class ParcoursMediaController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/parcours/{id}/photo', name: 'parcours_upload_photo', methods: ['POST'])]
    #[IsGranted('PARCOURS_EDIT', subject: 'parcours')]
    public function photo(Parcours $parcours, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException("Aucun fichier reçu (champ 'file' attendu).");
        }

        $parcours->setPhotoFile($file);
        $this->em->flush();

        $json = $this->serializer->serialize($parcours, 'json', ['groups' => ['parcours:read']]);

        return JsonResponse::fromJsonString($json);
    }
}
