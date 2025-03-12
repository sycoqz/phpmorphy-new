<?php

namespace Interfaces;

use Traversable;

interface CollectionInterface
{
    public function import(Traversable $values);

    public function append($value);

    public function clear();
}
