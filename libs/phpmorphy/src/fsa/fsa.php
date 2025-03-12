<?php

use Interfaces\FsaInterface;

abstract class phpMorphy_Fsa implements FsaInterface {
    const HEADER_SIZE = 128;

    protected
        $resource,
        $header;
    protected array|int $root_trans;
    protected array $alphabet;
    protected mixed $fsa_start;

    protected function __construct($resource, $header) {
        $this->resource = $resource;
        $this->header = $header;
        $this->fsa_start = $header['fsa_offset'];
        $this->root_trans = $this->readRootTrans();
    }

    /**
     * @throws phpMorphy_Exception
     */
    static function create(phpMorphy_Storage $storage, $lazy) {
        if($lazy) {
            return new phpMorphy_Fsa_Proxy($storage);
        }

        $header = phpMorphy_Fsa::readHeader(
            $storage->read(0, self::HEADER_SIZE, true)
        );

        if(!phpMorphy_Fsa::validateHeader($header)) {
            throw new phpMorphy_Exception('Invalid fsa format');
        }

        if($header['flags']['is_sparse']) {
            $type = 'sparse';
        } else if($header['flags']['is_tree']) {
            $type = 'tree';
        } else {
            throw new phpMorphy_Exception('Only sparse or tree fsa`s supported');
        }

        $storage_type = $storage->getTypeAsString();
        $file_path = dirname(__FILE__) . "/access/fsa_{$type}_{$storage_type}.php";
        $clazz = 'phpMorphy_Fsa_' . ucfirst($type) . '_' . ucfirst($storage_type);

        require_once($file_path);
        return new $clazz(
            $storage->getResource(),
            $header
        );
    }

    public function getRootTrans(): array|int
    {
        return $this->root_trans;
    }

    public function getRootState(): phpMorphy_State
    {
        return $this->createState($this->getRootStateIndex());
    }

    public function getAlphabet(): array
    {
        if(!isset($this->alphabet)) {
            $this->alphabet = str_split($this->readAlphabet());
        }

        return $this->alphabet;
    }

    protected function createState($index): phpMorphy_State
    {
        require_once(PHPMORPHY_DIR . '/fsa/fsa_state.php');
        return new phpMorphy_State($this, $index);
    }

    /**
     * @throws phpMorphy_Exception
     */
    static protected function readHeader($headerRaw): array
    {
        if($GLOBALS['__phpmorphy_strlen']($headerRaw) != self::HEADER_SIZE) {
            throw new phpMorphy_Exception('Invalid header string given');
        }

        $header = unpack(
            'a4fourcc/Vver/Vflags/Valphabet_offset/Vfsa_offset/Vannot_offset/Valphabet_size/Vtranses_count/Vannot_length_size/' .
            'Vannot_chunk_size/Vannot_chunks_count/Vchar_size/Vpadding_size/Vdest_size/Vhash_size',
            $headerRaw
        );

        if(false === $header) {
            throw new phpMorphy_Exception('Can`t unpack header');
        }

        $flags = array();
        $raw_flags = $header['flags'];
        $flags['is_tree'] = (bool)($raw_flags & 0x01);
        $flags['is_hash'] = (bool)($raw_flags & 0x02);
        $flags['is_sparse'] = (bool)($raw_flags & 0x04);
        $flags['is_be'] = (bool)($raw_flags & 0x08);

        $header['flags'] = $flags;

        $header['trans_size'] = $header['char_size'] + $header['padding_size'] + $header['dest_size'] + $header['hash_size'];

        return $header;
    }

    // static
    static protected function validateHeader($header): bool
    {
        if(
            'meal' != $header['fourcc'] ||
            3 != $header['ver'] ||
            $header['char_size'] != 1 ||
            $header['padding_size'] > 0 ||
            $header['dest_size'] != 3 ||
            $header['hash_size'] != 0 ||
            $header['annot_length_size'] != 1 ||
            $header['annot_chunk_size'] != 1 ||
            $header['flags']['is_be'] ||
            $header['flags']['is_hash'] ||
            1 == 0
        ) {
            return false;
        }

        return true;
    }

    protected function getRootStateIndex(): int
    { return 0; }

    abstract protected function readRootTrans();
    abstract protected function readAlphabet();
};

class phpMorphy_Fsa_WordsCollector {
    protected $limit;
    protected array $items = [];

    public function __construct($collectLimit) {
        $this->limit = $collectLimit;
    }

    public function collect($word, $annot): bool
    {
        if(count($this->items) < $this->limit) {
            $this->items[$word] = $annot;
            return true;
        } else {
            return false;
        }
    }

    public function getItems(): array
    { return $this->items; }
    public function clear(): void
    { $this->items = array(); }
    public function getCallback(): array
    { return array($this, 'collect'); }
};

class phpMorphy_Fsa_Decorator implements FsaInterface {
    protected FsaInterface $fsa;

    public function __construct(FsaInterface $fsa) {
        $this->fsa = $fsa;
    }

    public function getRootTrans(): array|int
    { return $this->fsa->getRootTrans(); }
    public function getRootState() { return $this->fsa->getRootState(); }
    public function getAlphabet(): array
    { return $this->fsa->getAlphabet(); }
    public function getAnnot(array $trans): string
    { return $this->fsa->getAnnot($trans); }
    public function walk(mixed $trans, string $word, bool $readAnnot = true): bool|array
    { return $this->fsa->walk($trans, $word, $readAnnot); }
    public function collect(mixed $startNode, mixed $callback, bool $readAnnot = true, string $path = '') { return $this->fsa->collect($startNode, $callback, $readAnnot, $path); }
    public function readState(int $index): array
    { return $this->fsa->readState($index); }
    public function unpackTranses(array $rawTranses): array
    { return $this->fsa->unpackTranses($rawTranses); }
};

class phpMorphy_Fsa_Proxy extends phpMorphy_Fsa_Decorator {
    protected phpMorphy_Storage $storage;

    public function __construct(phpMorphy_Storage $storage) {
        $this->storage = $storage;
        unset($this->fsa);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function __get($propName) {
        if($propName == 'fsa') {
            $this->fsa = phpMorphy_Fsa::create($this->storage, false);

            unset($this->storage);
            return $this->fsa;
        }

        throw new phpMorphy_Exception("Unknown prop name '$propName'");
    }
}
