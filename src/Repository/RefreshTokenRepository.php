<?php

namespace App\Repository;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as BaseRefreshTokenRepository;

/**
 * @extends BaseRefreshTokenRepository<\App\Entity\RefreshToken>
 */
class RefreshTokenRepository extends BaseRefreshTokenRepository
{
}
