<?php

namespace App\Services\Voucher;

use RuntimeException;

/**
 * Raised when a batch cannot be issued: exhausted sequence space, or a
 * collision a non-rerollable pattern cannot escape.
 */
class CodeGenerationException extends RuntimeException
{
}
