<?php

namespace App\Controller\Auth;

use Symfony\Component\Routing\Attribute\Route;

final class RefreshTokenController
{
    #[Route('/token/refresh', name: 'token_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        // Interceptée par le firewall "refresh" (refresh-jwt) avant d'atteindre ce contrôleur.
        throw new \LogicException('Cette route est gérée par le firewall refresh-jwt.');
    }
}
