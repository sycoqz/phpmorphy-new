<?php

require_once dirname(__FILE__).'/../../libs/collections.php';

class phpMorphy_Dict_Ancode
{
    protected $id;

    protected $grammems;

    protected $pos;

    protected $is_predict;

    public function __construct($id, $pos, $isPredict, $grammems = null)
    {
        // self::checkAncodeId($id, "Invalid ancode_id specified in ancode ctor");

        $this->grammems = new phpMorphy_Collection;

        if (is_string($grammems)) {
            $this->setGrammemsFromString($grammems);
        } elseif (is_array($grammems)) {
            $this->grammems->import(new ArrayIterator($grammems));
        } elseif (! is_null($grammems)) {
            throw new phpMorphy_Exception('Invalid grammems given');
        }

        $this->id = $id;
        $this->pos = $pos;
        $this->is_predict = (bool) $isPredict;
    }

    /*
        static function checkAncodeId($id, $prefix) {
            if(strlen($id) != 2) {
                throw new Exception("$prefix: Ancode must be exact 2 bytes long, '$id' given");
            }
        }
    */

    public function getGrammems()
    {
        return $this->grammems->getIterator();
    }

    public function setGrammemsFromString($grammems, $separator = ',')
    {
        $this->grammems->import(new ArrayIterator(array_map('trim', explode(',', $grammems))));
    }

    public function addGrammem($grammem)
    {
        $this->grammems->append($grammem);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPartOfSpeech()
    {
        return $this->pos;
    }

    public function isPredict()
    {
        return $this->is_predict;
    }

    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

interface phpMorphy_Dict_Flexia_Interface
{
    public function getPrefix();

    public function getSuffix();

    public function getAncodeId();

    public function setPrefix($prefix);
}

class phpMorphy_Dict_Flexia implements phpMorphy_Dict_Flexia_Interface
{
    protected $prefix;

    protected $suffix;

    protected $ancode_id;

    public function __construct($prefix, $suffix, $ancodeId)
    {
        // phpMorphy_Dict_Ancode::checkAncodeId($ancodeId, "Invalid ancode specified for flexia");

        $this->prefix = (string) $prefix;
        $this->suffix = (string) $suffix;
        $this->ancode_id = $ancodeId;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getSuffix()
    {
        return $this->suffix;
    }

    public function getAncodeId()
    {
        return $this->ancode_id;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}

class phpMorphy_Dict_FlexiaModel extends phpMorphy_Collection /* _Typed */
{
    protected $id;

    public function __construct($id)
    {
        parent::__construct(/* $this->createStorageCollection(), 'phpMorphy_Dict_Flexia' */);
        $this->id = (int) $id;

        if ($this->id < 0) {
            throw new Exception('Flexia model id must be positive int');
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getFlexias()
    {
        return iterator_to_array($this);
    }

    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

class phpMorphy_Dict_PrefixSet extends phpMorphy_Collection /* _Typed */
{
    protected $id;

    public function __construct($id)
    {
        parent::__construct(/* $this->createStorageCollection(), 'string' */);

        $this->id = (int) $id;

        if ($this->id < 0) {
            throw new Exception('Prefix set id must be positive int');
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPrefixes()
    {
        return $this->getIterator();
    }

    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

class phpMorphy_Dict_AccentModel extends phpMorphy_Collection /* _Typed */
{
    protected $id;

    public function __construct($id)
    {
        parent::__construct(/* $this->createStorageCollection(), array('integer', 'NULL') */);

        $this->id = (int) $id;

        if ($this->id < 0) {
            throw new Exception('Accent model id must be positive int');
        }
    }

    public function append($offset)
    {
        if ($offset === null) {
            $this->addEmptyAccent();
        } else {
            parent::append((int) $offset);
        }
    }

    public function addEmptyAccent()
    {
        parent::append(null);
    }

    public static function isEmptyAccent($accent)
    {
        return $accent === null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAccents()
    {
        return $this->getIterator();
    }

    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

interface phpMorphy_Dict_Lemma_Interface
{
    public function setPrefixId($prefixId);

    public function setAncodeId($ancodeId);

    public function getBase();

    public function getFlexiaId();

    public function getAccentId();

    public function getPrefixId();

    public function getAncodeId();

    public function hasPrefixId();

    public function hasAncodeId();
}

class phpMorphy_Dict_Lemma implements phpMorphy_Dict_Lemma_Interface
{
    protected $base;

    protected $flexia_id;

    protected $accent_id;

    protected $prefix_id;

    protected $ancode_id;

    public function __construct($base, $flexiaId, $accentId)
    {
        $this->base = (string) $base;
        $this->flexia_id = (int) $flexiaId;
        $this->accent_id = (int) $accentId;

        if ($this->flexia_id < 0) {
            throw new Exception('flexia_id must be positive int');
        }

        if ($this->accent_id < 0) {
            throw new Exception('accent_id must be positive int');
        }
    }

    public function setPrefixId($prefixId)
    {
        if (is_null($prefixId)) {
            throw new phpMorphy_Exception('NULL id specified');
        }

        $this->prefix_id = (int) $prefixId;

        if ($this->prefix_id < 0) {
            throw new Exception('prefix_id must be positive int');
        }
    }

    public function setAncodeId($ancodeId)
    {
        if (is_null($ancodeId)) {
            throw new Exception('NULL id specified');
        }

        // phpMorphy_Dict_Ancode::checkAncodeId($ancodeId, "Invalid ancode specified for lemma");

        $this->ancode_id = $ancodeId;
    }

    public function getBase()
    {
        return $this->base;
    }

    public function getFlexiaId()
    {
        return $this->flexia_id;
    }

    public function getAccentId()
    {
        return $this->accent_id;
    }

    public function getPrefixId()
    {
        return $this->prefix_id;
    }

    public function getAncodeId()
    {
        return $this->ancode_id;
    }

    public function hasPrefixId()
    {
        return isset($this->prefix_id);
    }

    public function hasAncodeId()
    {
        return isset($this->ancode_id);
    }
}
