<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class BranchLimitExceededException extends Exception
{
    public function __construct(
        string $message = 'Your current plan does not allow adding more branches. Please upgrade to Premium.'
    ) {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }
}
