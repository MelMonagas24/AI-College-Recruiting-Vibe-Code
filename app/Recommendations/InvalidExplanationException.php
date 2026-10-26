<?php

namespace App\Recommendations;

use RuntimeException;

/**
 * Thrown when an AI response does not stick to the verified facts it was given.
 */
class InvalidExplanationException extends RuntimeException {}
