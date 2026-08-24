<?php

namespace App\Controller\Auth;

use Symfony\Component\Routing\Attribute\Route;

final class LoginController
{
    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(): never
    {
        // Interceptée par le firewall "login" (json_login) avant d'atteindre ce contrôleur.
        throw new \LogicException('Cette route est gérée par le firewall json_login.');
    }
}
