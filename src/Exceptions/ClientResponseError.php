<?php

namespace BosBase\Exceptions;

class ClientResponseError extends \Exception
{
    public ?string $url;
    public int $status;
    public array $response;
    public bool $isAbort;
    public ?\Throwable $originalError;

    public function __construct(
        ?string $url = null,
        int $status = 0,
        array $response = [],
        bool $isAbort = false,
        ?\Throwable $originalError = null
    ) {
        $this->url = $url;
        $this->status = $status;
        $this->response = $response;
        $this->isAbort = $isAbort;
        $this->originalError = $originalError;

        $message = sprintf(
            'ClientResponseError(status=%d, url=%s)',
            $status,
            $url ?? ''
        );

        parent::__construct($message, $status, $originalError);
    }
}
