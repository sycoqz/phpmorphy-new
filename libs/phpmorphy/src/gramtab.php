<?php

use Interfaces\GramTabInterface;

class phpMorphy_GramTab_Empty implements GramTabInterface {
    public function getGrammems($ancodeId): array
    { return []; }
    public function getPartOfSpeech(int $ancodeId): int
    { return 0; }
    public function resolveGrammemIds($ids): array|string
    { return is_array($ids) ? [] : ''; }
    public function resolvePartOfSpeechId($id): string
    { return ''; }
    public function includeConsts(): void { }
    public function ancodeToString($ancodeId, $commonAncode = null): string
    { return ''; }
    public function stringToAncode($string) { return null; }
    public function toString($partOfSpeechId, $grammemIds): string
    { return ''; }
}

class phpMorphy_GramTab_Proxy implements GramTabInterface {
    protected phpMorphy_GramTab $__obj;
    protected phpMorphy_Storage $storage;

    public function __construct(phpMorphy_Storage $storage) {
        $this->storage = $storage;
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getGrammems(int $ancodeId) {
        return $this->getObj()->getGrammems($ancodeId);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getPartOfSpeech(int $ancodeId) {
        return $this->getObj()->getPartOfSpeech($ancodeId);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function resolveGrammemIds($ids) {
        return $this->getObj()->resolveGrammemIds($ids);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function resolvePartOfSpeechId($id) {
        return $this->getObj()->resolvePartOfSpeechId($id);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function includeConsts(): void
    {
        $this->getObj()->includeConsts();
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function ancodeToString($ancodeId, $commonAncode = null): string
    {
        return $this->getObj()->ancodeToString($ancodeId, $commonAncode);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function stringToAncode($string) {
        return $this->getObj()->stringToAncode($string);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function toString($partOfSpeechId, $grammemIds): string
    {
        return $this->getObj()->toString($partOfSpeechId, $grammemIds);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getObj(): phpMorphy_GramTab
    {
        if (isset($this->__obj)) {
            return $this->__obj;
        }
        $this->__obj = phpMorphy_GramTab::create($this->storage);
        unset($this->storage);

        return $this->__obj;
    }
}

class phpMorphy_GramTab implements GramTabInterface {
    protected $data, $grammems, $__ancodes_map, $poses;

    protected array $ancodes;

    /**
     * @throws phpMorphy_Exception
     */
    protected function __construct(phpMorphy_Storage $storage) {
        $this->data = unserialize($storage->read(0, $storage->getFileSize()));

        if(false === $this->data) {
            throw new phpMorphy_Exception("Broken gramtab data");
        }

        $this->grammems = $this->data['grammems'];
        $this->poses = $this->data['poses'];
        $this->ancodes = $this->data['ancodes'];
    }

    // TODO: remove this

    /**
     * @throws phpMorphy_Exception
     */
    static function create(phpMorphy_Storage $storage): phpMorphy_GramTab
    {
        return new phpMorphy_GramTab($storage);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getGrammems(int $ancodeId) {
        if(!isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }

        return $this->ancodes[$ancodeId]['grammem_ids'];
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getPartOfSpeech(int $ancodeId) {
        if(!isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }

        return $this->ancodes[$ancodeId]['pos_id'];
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function resolveGrammemIds(array|int $ids) {
        if (is_array($ids)) {
            $result = array();

            foreach($ids as $id) {
                if(!isset($this->grammems[$id])) {
                    throw new phpMorphy_Exception("Invalid grammem id '$id'");
                }

                $result[] = $this->grammems[$id]['name'];
            }

            return $result;
        } else {
            if(!isset($this->grammems[$ids])) {
                throw new phpMorphy_Exception("Invalid grammem id '$ids'");
            }

            return $this->grammems[$ids]['name'];
        }
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function resolvePartOfSpeechId($id) {
        if(!isset($this->poses[$id])) {
            throw new phpMorphy_Exception("Invalid part of speech id '$id'");
        }

        return $this->poses[$id]['name'];
    }

    public function includeConsts(): void
    {
        require_once(PHPMORPHY_DIR . '/gramtab_consts.php');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function ancodeToString($ancodeId, $commonAncode = null): string
    {
        if(isset($commonAncode)) {
            $commonAncode = implode(',', $this->getGrammems($commonAncode)) . ',';
        }

        return
            $this->getPartOfSpeech($ancodeId) . ' ' .
            $commonAncode .
            implode(',', $this->getGrammems($ancodeId));
    }


    /**
     * @throws phpMorphy_Exception
     */
    public function stringToAncode($string) {
        if(!isset($string)) {
            return null;
        }

        if ($this->__ancodes_map === null) {
            $this->__ancodes_map = $this->buildAncodesMap();
        }

        if(!isset($this->__ancodes_map[$string])) {
            throw new phpMorphy_Exception("Ancode with '$string' graminfo not found");
        }

        return $this->__ancodes_map[$string];
    }

    public function toString($partOfSpeechId, $grammemIds): string
    {
        return $partOfSpeechId . ' ' . implode(',', $grammemIds);
    }

    protected function buildAncodesMap(): array
    {
        $result = array();

        foreach($this->ancodes as $ancode_id => $data) {
            $key = $this->toString($data['pos_id'], $data['grammem_ids']);

            $result[$key] = $ancode_id;
        }

        return $result;
    }
}
