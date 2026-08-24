<?php

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert en dev les fichiers stockés hors du webroot (logos, couvertures,
 * photos de parcours, JSON par organisation), afin qu'ils passent par le
 * kernel Symfony et bénéficient de NelmioCorsBundle. En production, ces
 * mêmes URL pointeront directement vers S3/CloudFront : ce contrôleur n'a
 * alors plus lieu d'être appelé.
 */
final class MediaController
{
    #[Route('/media/logos/{filename}', name: 'media_organization_logo', methods: ['GET'])]
    public function organizationLogo(
        string $filename,
        #[Autowire(service: 'organization_logo.storage')] FilesystemOperator $storage,
    ): StreamedResponse {
        return $this->serve($storage, $filename);
    }

    #[Route('/media/covers/{filename}', name: 'media_organization_cover', methods: ['GET'])]
    public function organizationCover(
        string $filename,
        #[Autowire(service: 'organization_cover.storage')] FilesystemOperator $storage,
    ): StreamedResponse {
        return $this->serve($storage, $filename);
    }

    #[Route('/media/parcours/{filename}', name: 'media_parcours_photo', methods: ['GET'])]
    public function parcoursPhoto(
        string $filename,
        #[Autowire(service: 'parcours_photo.storage')] FilesystemOperator $storage,
    ): StreamedResponse {
        return $this->serve($storage, $filename);
    }

    #[Route('/media/parcours-points/{filename}', name: 'media_parcours_point', methods: ['GET'])]
    public function parcoursPointMedia(
        string $filename,
        #[Autowire(service: 'parcours_point_media.storage')] FilesystemOperator $storage,
    ): StreamedResponse {
        return $this->serve($storage, $filename);
    }

    #[Route('/data/{filename}', name: 'media_organization_data', methods: ['GET'], requirements: ['filename' => '[a-z0-9-]+\.json'])]
    public function organizationData(
        string $filename,
        #[Autowire(service: 'organization_data.storage')] FilesystemOperator $storage,
    ): StreamedResponse {
        return $this->serve($storage, $filename);
    }

    private function serve(FilesystemOperator $storage, string $filename): StreamedResponse
    {
        try {
            $stream = $storage->readStream($filename);
            $mimeType = $storage->mimeType($filename);
        } catch (UnableToReadFile) {
            throw new NotFoundHttpException();
        }

        $response = new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
        });
        $response->headers->set('Content-Type', $mimeType);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
