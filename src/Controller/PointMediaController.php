<?php

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Média attaché à un point de parcours : image ou son, rien d'autre.
 *
 * La vidéo passe par YouTube ou Dailymotion (champ videoUrl du point) : une
 * vidéo auto-hébergée pèse une cinquantaine de mégaoctets, ce qui exclut de la
 * précharger pour le hors-ligne et coûterait plus cher en diffusion que
 * l'abonnement annuel d'une organisation.
 *
 * Volontairement non scopé à un parcours précis : les points sont recréés à
 * chaque sauvegarde (voir ParcoursWriteProcessor), donc pas d'id de point
 * stable à viser, et cela permet d'ajouter des médias dès la création du
 * parcours, avant son premier enregistrement.
 */
final class PointMediaController
{
    /**
     * Types acceptés et plafond de poids, par nature de média.
     *
     * Sans cette liste, le point d'entrée acceptait n'importe quel fichier de
     * n'importe quelle taille : un espace de stockage libre pour tout compte
     * authentifié. Les plafonds tiennent compte de l'usage réel — une photo de
     * façade, un commentaire audio de deux ou trois minutes.
     */
    private const array TYPES = [
        'image' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],
            'max' => 5 * 1024 * 1024,
            'libelle' => '5 Mo',
        ],
        'audio' => [
            'mimes' => ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm'],
            'max' => 20 * 1024 * 1024,
            'libelle' => '20 Mo',
        ],
    ];

    #[Route('/point-media', name: 'point_media_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(
        Request $request,
        #[Autowire(service: 'parcours_point_media.storage')] FilesystemOperator $storage,
    ): JsonResponse {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException("Aucun fichier reçu (champ 'file' attendu).");
        }

        // Un envoi qui depasse upload_max_filesize arrive ici en erreur : le
        // fichier temporaire est absent, et toute lecture derriere leve une
        // exception — d'ou une 500 la ou l'utilisateur attend une explication.
        if (!$file->isValid()) {
            throw new BadRequestHttpException(match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => sprintf(
                    'Fichier trop lourd. Maximum %s pour une image, %s pour un fichier audio.',
                    self::TYPES['image']['libelle'],
                    self::TYPES['audio']['libelle'],
                ),
                \UPLOAD_ERR_PARTIAL => "L'envoi a ete interrompu. Reessayez.",
                \UPLOAD_ERR_NO_FILE => 'Aucun fichier recu.',
                default => "L'envoi a echoue.",
            });
        }

        // getMimeType() lit la signature du fichier, il ne fait pas confiance
        // au nom ni à l'en-tête envoyés par le client.
        $mime = $file->getMimeType() ?? '';
        $type = null;
        foreach (self::TYPES as $nom => $regles) {
            if (in_array($mime, $regles['mimes'], true)) {
                $type = $nom;
                break;
            }
        }

        if (null === $type) {
            throw new BadRequestHttpException(sprintf(
                'Type de fichier non accepté (%s). Images et fichiers audio uniquement ; '
                .'pour une vidéo, collez un lien YouTube ou Dailymotion sur le point.',
                $mime ?: 'inconnu',
            ));
        }

        $regles = self::TYPES[$type];
        if ($file->getSize() > $regles['max']) {
            throw new BadRequestHttpException(sprintf(
                'Fichier trop lourd (%s). Maximum %s pour %s.',
                self::formatPoids($file->getSize()),
                $regles['libelle'],
                'image' === $type ? 'une image' : 'un fichier audio',
            ));
        }

        $extension = $file->guessExtension() ?? 'bin';
        $filename = sprintf('%s.%s', Uuid::v7()->toRfc4122(), $extension);

        $stream = fopen($file->getPathname(), 'r');
        $storage->writeStream($filename, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return new JsonResponse([
            'path' => '/media/parcours-points/'.$filename,
            'type' => $type,
        ]);
    }

    private static function formatPoids(?int $octets): string
    {
        $octets ??= 0;

        return $octets >= 1024 * 1024
            ? sprintf('%.1f Mo', $octets / 1024 / 1024)
            : sprintf('%d Ko', (int) ceil($octets / 1024));
    }
}
