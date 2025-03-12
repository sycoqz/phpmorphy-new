<?php

namespace Interfaces;

interface MorphierFinderInterface
{
    public function findWord($word);
    public function decodeAnnot($raw, $withBase);
    public function getAnnotDecoder();
}
