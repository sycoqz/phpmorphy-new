<?php

namespace Interfaces;

interface AncodesResolverInterface
{
    public function resolve($ancodeId);
    public function unresolve($ancode);
}
