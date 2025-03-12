<?php

namespace Interfaces;

interface DictWriterObserverInterface
{
    public function onStart();

    public function onLog($message);

    public function onEnd();
}
