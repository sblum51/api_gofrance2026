<?php

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Média attaché à un point de parcours (route ou point connexe). Volontairement
 * non scopé à un parcours précis : les points sont recréés à chaque
 * sauvegarde (voir ParcoursWriteProcessor), donc pas d'id de point stable à
 * viser, et cela permet d'ajouter des médias dès la création du parcours,
 * avant son premier enregistrement. L'upload renvoie juste un chemin, que
 * MANAGER ajoute au tableau `media` du point puis soumet avec le reste du
 * parcours.
 */
final class PointMediaController
{
    #[Route('/point-media', name: 'point_media_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(
        Request $request,
        #[Autowire(service: 'parcours_point_media.storage')] FilesystemOperator $storage,
    ): JsonResponse {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException("Aucun fichier reçu (champ 'file' attendu).");
        }

        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension() ?? 'bin';
        $filename = sprintf('%s.%s', Uuid::v7()->toRfc4122(), $extension);

        $stream = fopen($file->getPathname(), 'r');
        $storage->writeStream($filename, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return new JsonResponse(['path' => '/media/parcours-points/'.$filename]);
    }
}
