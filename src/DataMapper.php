<?php

namespace Jasny\DB\REST;

use Jasny\DB\Dataset;

/**
 * Data Mapper for fetching and storing entities using REST.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
interface DataMapper extends Jasny\DB\DataMapper, Dataset
{
}
