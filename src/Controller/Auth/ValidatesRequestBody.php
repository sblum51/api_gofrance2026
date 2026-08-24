<?php

namespace App\Controller\Auth;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidatesRequestBody
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|JsonResponse
     */
    private function parseAndValidate(
        Request $request,
        string $class,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): object {
        try {
            $dto = $serializer->deserialize($request->getContent() ?: '{}', $class, 'json');
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Corps de requête JSON invalide.'], 400);
        }

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['message' => 'Données invalides.', 'errors' => $errors], 422);
        }

        return $dto;
    }
}
