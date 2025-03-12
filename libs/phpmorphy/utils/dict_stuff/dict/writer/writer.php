<?php

use Interfaces\DictWriterObserverInterface;

require_once dirname(__FILE__).'/../source/source.php';

class phpMorphy_Dict_Writer_Observer_Empty implements DictWriterObserverInterface
{
    public function onStart() {}

    public function onLog($message) {}

    public function onEnd() {}
}

class phpMorphy_Dict_Writer_Observer_Standart implements DictWriterObserverInterface
{
    protected $start_time;

    /**
     * @throws Exception
     */
    public function __construct($callback)
    {
        if (! is_callable($callback)) {
            throw new Exception('Invalid callback');
        }

        $this->callback = $callback;
    }

    public function onStart(): void
    {
        $this->start_time = microtime(true);
    }

    public function onEnd(): void
    {
        $this->writeMessage(sprintf('Total time = %f', microtime(true) - $this->start_time));
    }

    public function onLog($message): void
    {
        $this->writeMessage(sprintf('+%0.2f %s', microtime(true) - $this->start_time, $message));
    }

    protected function writeMessage($msg): void
    {
        call_user_func($this->callback, $msg);
    }
}

abstract class phpMorphy_Dict_Writer_Base
{
    private $observer;

    public function __construct()
    {
        $this->setObserver(new phpMorphy_Dict_Writer_Observer_Empty);
    }

    public function setObserver(DictWriterObserverInterface $observer): void
    {
        $this->observer = $observer;
    }

    public function hasObserver(): bool
    {
        return isset($this->observer);
    }

    public function getObserver()
    {
        return $this->observer;
    }

    protected function log($message): void
    {
        if ($this->hasObserver()) {
            $this->getObserver()->onLog($message);
        }
    }
}
