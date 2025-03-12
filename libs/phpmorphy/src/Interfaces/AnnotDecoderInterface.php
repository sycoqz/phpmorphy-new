<?php

namespace Interfaces;

interface AnnotDecoderInterface
{
    public function decode($annotRaw, $withBase);
}
