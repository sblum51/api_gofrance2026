<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Le mapping des champs (id, refreshToken, username, valid) vient de la
 * mapped-superclass XML fournie par le bundle (auto-enregistrée via
 * auto_mapping) : ne pas redéclarer ces colonnes ici sous peine de conflit
 * Doctrine ("Duplicate definition of column 'id'").
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
