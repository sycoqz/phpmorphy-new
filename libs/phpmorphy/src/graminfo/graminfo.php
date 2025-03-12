<?php

use Interfaces\GramInfoInterface;

abstract class phpMorphy_GramInfo implements GramInfoInterface
{
    const HEADER_SIZE = 128;

    protected $resource;

    protected $header;

    protected $ends_size;

    protected string $ends;

    protected function __construct($resource, $header)
    {
        $this->resource = $resource;
        $this->header = $header;

        $this->ends = str_repeat("\0", $header['char_size'] + 1);
        $this->ends_size = $GLOBALS['__phpmorphy_strlen']($this->ends);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public static function create(phpMorphy_Storage $storage, $lazy)
    {
        if ($lazy) {
            return new phpMorphy_GramInfo_Proxy($storage);
        }

        $header = phpMorphy_GramInfo::readHeader(
            $storage->read(0, self::HEADER_SIZE)
        );

        if (! phpMorphy_GramInfo::validateHeader($header)) {
            throw new phpMorphy_Exception('Invalid graminfo format');
        }

        $storage_type = $storage->getTypeAsString();
        $file_path = dirname(__FILE__)."/access/graminfo_{$storage_type}.php";
        $clazz = 'phpMorphy_GramInfo_'.ucfirst($storage_type);

        require_once $file_path;

        return new $clazz($storage->getResource(), $header);
    }

    public function getLocale(): string
    {
        return $this->header['lang'];
    }

    public function getEncoding(): string
    {
        return $this->header['encoding'];
    }

    public function getCharSize(): int
    {
        return $this->header['char_size'];
    }

    public function getEnds(): string
    {
        return $this->ends;
    }

    public function getHeader()
    {
        return $this->header;
    }

    protected static function readHeader($headerRaw): array
    {
        $header = unpack(
            'Vver/Vis_be/Vflex_count_old/'.
            'Vflex_offset/Vflex_size/Vflex_count/Vflex_index_offset/Vflex_index_size/'.
            'Vposes_offset/Vposes_size/Vposes_count/Vposes_index_offset/Vposes_index_size/'.
            'Vgrammems_offset/Vgrammems_size/Vgrammems_count/Vgrammems_index_offset/Vgrammems_index_size/'.
            'Vancodes_offset/Vancodes_size/Vancodes_count/Vancodes_index_offset/Vancodes_index_size/'.
            'Vchar_size/',
            $headerRaw
        );

        $offset = 24 * 4;
        $len = ord($GLOBALS['__phpmorphy_substr']($headerRaw, $offset++, 1));
        $header['lang'] = rtrim($GLOBALS['__phpmorphy_substr']($headerRaw, $offset, $len));

        $offset += $len;

        $len = ord($GLOBALS['__phpmorphy_substr']($headerRaw, $offset++, 1));
        $header['encoding'] = rtrim($GLOBALS['__phpmorphy_substr']($headerRaw, $offset, $len));

        return $header;
    }

    protected static function validateHeader($header)
    {
        if (
            $header['ver'] != 3 ||
            $header['is_be'] == 1
        ) {
            return false;
        }

        return true;
    }

    protected function cleanupCString($string)
    {
        if (false !== ($pos = $GLOBALS['__phpmorphy_strpos']($string, $this->ends))) {
            $string = $GLOBALS['__phpmorphy_substr']($string, 0, $pos);
        }

        return $string;
    }

    abstract protected function readSectionIndex($offset, $count);

    protected function readSectionIndexAsSize($offset, $count, $total_size)
    {
        if (! $count) {
            return [];
        }

        $index = $this->readSectionIndex($offset, $count);
        $index[$count] = $index[0] + $total_size;

        for ($i = 0; $i < $count; $i++) {
            $index[$i] = $index[$i + 1] - $index[$i];
        }

        unset($index[$count]);

        return $index;
    }
}

class phpMorphy_GramInfo_Decorator implements GramInfoInterface
{
    protected GramInfoInterface $info;

    public function __construct(GramInfoInterface $info)
    {
        $this->info = $info;
    }

    public function readGramInfoHeader(int $offset): array|bool
    {
        return $this->info->readGramInfoHeader($offset);
    }

    public function getGramInfoHeaderSize()
    {
        return $this->info->getGramInfoHeaderSize();
    }

    public function readAncodes($info): array
    {
        return $this->info->readAncodes($info);
    }

    public function readFlexiaData(array $info): array
    {
        return $this->info->readFlexiaData($info);
    }

    public function readAllGramInfoOffsets(): array
    {
        return $this->info->readAllGramInfoOffsets();
    }

    public function readAllPartOfSpeech()
    {
        return $this->info->readAllPartOfSpeech();
    }

    public function readAllGrammems()
    {
        return $this->info->readAllGrammems();
    }

    public function readAllAncodes()
    {
        return $this->info->readAllAncodes();
    }

    public function getLocale(): string
    {
        return $this->info->getLocale();
    }

    public function getEncoding(): string
    {
        return $this->info->getEncoding();
    }

    public function getCharSize(): int
    {
        return $this->info->getCharSize();
    }

    public function getEnds(): string
    {
        return $this->info->getEnds();
    }

    public function getHeader()
    {
        return $this->info->getHeader();
    }
}

class phpMorphy_GramInfo_Proxy extends phpMorphy_GramInfo_Decorator
{
    protected phpMorphy_Storage $storage;

    public function __construct(phpMorphy_Storage $storage)
    {
        $this->storage = $storage;
        unset($this->info);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function __get($propName)
    {
        if ($propName == 'info') {
            $this->info = phpMorphy_GramInfo::create($this->storage, false);
            unset($this->storage);

            return $this->info;
        }

        throw new phpMorphy_Exception("Unknown prop name '$propName'");
    }
}

class phpMorphy_GramInfo_Proxy_WithHeader extends phpMorphy_GramInfo_Proxy
{
    protected $cache;

    protected string $ends;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct(phpMorphy_Storage $storage, string $cacheFile)
    {
        parent::__construct($storage);

        $this->cache = $this->readCache($cacheFile);
        $this->ends = str_repeat("\0", $this->getCharSize() + 1);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function readCache(string $fileName)
    {
        if (! is_array($result = include ($fileName))) {
            throw new phpMorphy_Exception("Can`t get header cache from '$fileName' file'");
        }

        return $result;
    }

    public function getLocale(): string
    {
        return $this->cache['lang'];
    }

    public function getEncoding(): string
    {
        return $this->cache['encoding'];
    }

    public function getCharSize(): int
    {
        return $this->cache['char_size'];
    }

    public function getEnds(): string
    {
        return $this->ends;
    }

    public function getHeader()
    {
        return $this->cache;
    }
}

class phpMorphy_GramInfo_RuntimeCaching extends phpMorphy_GramInfo_Decorator
{
    protected array $flexia = [];

    public function readFlexiaData(array $info): array
    {
        $offset = $info['offset'];

        if (! isset($this->flexia[$offset])) {
            $this->flexia[$offset] = $this->info->readFlexiaData($info);
        }

        return $this->flexia[$offset];
    }
}

class phpMorphy_GramInfo_AncodeCache extends phpMorphy_GramInfo_Decorator
{
    public int $miss = 0;

    public int $hits = 0;

    protected $cache;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct(GramInfoInterface $inner, $resource)
    {
        parent::__construct($inner);

        if (false === ($this->cache = unserialize($resource->read(0, $resource->getFileSize())))) {
            throw new phpMorphy_Exception('Can`t read ancodes cache');
        }
    }

    public function readAncodes($info): array
    {
        $offset = $info['offset'];

        if (isset($this->cache[$offset])) {
            $this->hits++;

            return $this->cache[$offset];
        } else {
            // in theory misses never occur
            $this->miss++;

            return parent::readAncodes($info);
        }
    }
}
