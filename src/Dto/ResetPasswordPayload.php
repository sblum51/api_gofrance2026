<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordPayload
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $password = '';
}
