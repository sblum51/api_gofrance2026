<?php

namespace App\Message;

final class GenerateOrganizationDataMessage
{
    public function __construct(public readonly string $organizationId)
    {
    }
}
