<?php

namespace Jasny\DB\REST;

use GuzzleHttp\Exception\BadResponseException;

/**
 * Exception when content doesn't match the Content-Type header
 */
class InvalidContentException extends BadResponseException
{
}
