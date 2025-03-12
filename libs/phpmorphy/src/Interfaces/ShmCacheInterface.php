<?php

namespace Interfaces;

interface ShmCacheInterface
{
    public function close();

    public function get($filePath);

    public function clear();

    public function delete($filePath);

    public function reload($filePath);

    public function reloadIfExists($filePath);

    public function free();
}
