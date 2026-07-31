<?php

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

/** Every message is returned to the uploader, so this is user-facing copy. */
class InvalidScheduleCsvException extends RuntimeException {}
