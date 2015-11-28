<?php

namespace Jasny\DB\REST\Resource;

use Jasny\DB\Entity,
    Jasny\DB\Dataset;

/**
 * REST resource as Active Record
 */
interface ActiveRecord extends
    Entity,
    Entity\ActiveRecord,
    Entity\Identifiable,
    Entity\SelfAware,
    Dataset
{ }
