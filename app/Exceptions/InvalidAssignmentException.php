<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when a ticket assignment would violate a domain/integrity rule.
 */
class InvalidAssignmentException extends DomainException {}
