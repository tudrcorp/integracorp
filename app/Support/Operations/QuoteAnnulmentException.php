<?php

declare(strict_types=1);

namespace App\Support\Operations;

use RuntimeException;

/**
 * Una regla impide anular la cotización. El mensaje va tal cual al analista, así
 * que se escribe en español y dice qué hacer.
 */
final class QuoteAnnulmentException extends RuntimeException {}
