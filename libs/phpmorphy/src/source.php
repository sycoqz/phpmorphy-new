<?php

use Interfaces\FsaInterface;
use Interfaces\SourceInterface;

const PHPMORPHY_SOURCE_FSA = 'fsa';
const PHPMORPHY_SOURCE_DBA = 'dba';
const PHPMORPHY_SOURCE_SQL = 'sql';


class phpMorphy_Source_Fsa implements SourceInterface {
    protected array|int $root;
    protected FsaInterface $fsa;

    public function __construct(FsaInterface $fsa) {
        $this->fsa = $fsa;
        $this->root = $fsa->getRootTrans();
    }

    public function getFsa(): FsaInterface
    {
    	return $this->fsa;
    }

    public function getValue(string $key) {
        if (false === ($result = $this->fsa->walk($this->root, $key)) || !$result['annot']) {
            return false;
        }

        return $result['annot'];
    }
}

class phpMorphy_Source_Dba implements SourceInterface {
    const DEFAULT_HANDLER = 'db3';

    protected $handle;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct($fileName, ?array $options = null) {
        $this->handle = $this->openFile($fileName, $this->repairOptions($options));
    }

    public function close(): void
    {
        if(isset($this->handle)) {
            dba_close($this->handle);
            $this->handle = null;
        }
    }

    static function getDefaultHandler(): string
    {
        return self::DEFAULT_HANDLER;
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function openFile(string $fileName, array $options) {
        if (false === ($new_filename = realpath($fileName))) {
            throw new phpMorphy_Exception("Can`t get realpath for '$fileName' file");
        }

        $lock_mode = $options['lock_mode'];
        $handler = $options['handler'];
        $func = $options['persistent'] ? 'dba_popen' : 'dba_open';

        if(false === ($result = $func($new_filename, "r$lock_mode", $handler))) {
            throw new phpMorphy_Exception("Can`t open '$fileName' file");
        }

        return $result;
    }

    protected function repairOptions(?array $options): array
    {
        $defaults = [
            'lock_mode' => 'd',
            'handler' => self::getDefaultHandler(),
            'persistent' => false
        ];

        return (array)$options + $defaults;
    }

    public function getValue(string $key): bool|string
    {
        return dba_fetch($key, $this->handle);
    }
}
