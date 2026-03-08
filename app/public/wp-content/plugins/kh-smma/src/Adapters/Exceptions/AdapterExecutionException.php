<?php

namespace KH_SMMA\Adapters\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AdapterExecutionException: thrown by paid adapters on unrecoverable provider errors.
 *
 * Callers should catch this and treat the execute() as failed (not retryable at
 * the adapter level). The idempotency key should NOT be consumed for an exception,
 * so the caller may retry after resolving the underlying issue.
 *
 * @see KH_SMMA\Adapters\PaidAdapterContract::execute()
 */
class AdapterExecutionException extends \RuntimeException {
}
