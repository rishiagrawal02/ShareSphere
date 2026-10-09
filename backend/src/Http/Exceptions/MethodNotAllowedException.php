<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class MethodNotAllowedException extends HttpException
{
    public function __construct(array $allowedMethods = ['GET'])
    {
        $methodsStr = implode(', ', $allowedMethods);
        $message = "Method not allowed. Allowed methods: $methodsStr";
        parent::__construct($message, 'METHOD_NOT_ALLOWED', 405, [], ['Allow' => $methodsStr]);
    }
}
