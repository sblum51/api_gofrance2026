<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgotPasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
