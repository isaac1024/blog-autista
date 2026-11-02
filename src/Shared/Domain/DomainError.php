<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Base of every error raised when a domain rule is broken, so adapters can
 * handle them apart from unexpected failures.
 */
abstract class DomainError extends \DomainException {}
