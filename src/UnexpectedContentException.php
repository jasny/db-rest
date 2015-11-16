<?php

namespace Jasny\DB\REST;

use GuzzleHttp\Exception\BadResponseException;

/**
 * Exception when content isn't what is expected
 */
class UnexpectedContentException extends BadResponseException
{
}
