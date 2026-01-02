<?php
/**
 * Result Value Object
 *
 * Represents the result of an operation (gateway charge, subscription creation, etc.).
 * Provides a consistent way to return success/failure with metadata.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

class Result {

    private bool $success;
    private ?string $message;
    private array $data;
    private ?string $errorCode;

    public function __construct( bool $success, ?string $message = null, array $data = [], ?string $errorCode = null ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->errorCode = $errorCode;
    }

    public static function success( ?string $message = null, array $data = [] ): self {
        return new self(true, $message, $data);
    }

    public static function failure( string $message, ?string $errorCode = null, array $data = [] ): self {
        return new self(false, $message, $data, $errorCode);
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function isFailure(): bool {
        return ! $this->success;
    }

    public function getMessage(): ?string {
        return $this->message;
    }

    public function getData(): array {
        return $this->data;
    }

    public function get( string $key, $default = null ) {
        return $this->data[ $key ] ?? $default;
    }

    public function getErrorCode(): ?string {
        return $this->errorCode;
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error_code' => $this->errorCode,
        ];
    }
}
