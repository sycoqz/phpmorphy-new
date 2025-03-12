<?php

use Interfaces\DictFlexiaInterface;
use Interfaces\DictLemmaInterface;

require_once dirname(__FILE__).'/../../libs/collections.php';

class phpMorphy_Dict_Ancode
{
    protected $id;

    protected phpMorphy_Collection $grammems;

    protected $pos;

    protected bool $is_predict;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct($id, $pos, bool $isPredict, $grammems = null)
    {

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
        $this->is_predict = $isPredict;
    }

    /**
     * @throws Exception
     */
    public function getGrammems(): ArrayIterator
    {
        return $this->grammems->getIterator();
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function setGrammemsFromString($grammems, $separator = ','): void
    {
        $this->grammems->import(new ArrayIterator(array_map('trim', explode(',', $grammems))));
    }

    public function addGrammem($grammem): void
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

    public function isPredict(): bool
    {
        return $this->is_predict;
    }
}

class phpMorphy_Dict_Flexia implements DictFlexiaInterface
{
    protected int $ancode_id;

    protected string $suffix;

    protected string $prefix;

    public function __construct(string $prefix, string $suffix, int $ancodeId)
    {
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->ancode_id = $ancodeId;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function getAncodeId(): int
    {
        return $this->ancode_id;
    }

    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix;
    }
}

class phpMorphy_Dict_FlexiaModel extends phpMorphy_Collection implements IteratorAggregate /* _Typed */
{
    protected int $id;

    /**
     * @throws Exception
     */
    public function __construct(int $id)
    {
        parent::__construct(/* $this->createStorageCollection(), 'phpMorphy_Dict_Flexia' */);
        $this->id = $id;

        if ($this->id < 0) {
            throw new Exception('Flexia model id must be positive int');
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFlexias(): array
    {
        return iterator_to_array($this);
    }
}

class phpMorphy_Dict_PrefixSet extends phpMorphy_Collection /* _Typed */
{
    protected int $id;

    /**
     * @throws Exception
     */
    public function __construct(int $id)
    {
        parent::__construct(/* $this->createStorageCollection(), 'string' */);

        $this->id = $id;

        if ($this->id < 0) {
            throw new Exception('Prefix set id must be positive int');
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @throws Exception
     */
    public function getPrefixes(): ArrayIterator
    {
        return $this->getIterator();
    }
}

class phpMorphy_Dict_AccentModel extends phpMorphy_Collection /* _Typed */
{
    protected int $id;

    /**
     * @throws Exception
     */
    public function __construct(int $id)
    {
        parent::__construct(/* $this->createStorageCollection(), array('integer', 'NULL') */);

        $this->id = $id;

        if ($this->id < 0) {
            throw new Exception('Accent model id must be positive int');
        }
    }

    public function append($value): void
    {
        if (! isset($value)) {
            $this->addEmptyAccent();
        } else {
            parent::append($value);
        }
    }

    public function addEmptyAccent(): void
    {
        parent::append(null);
    }

    public static function isEmptyAccent($accent): bool
    {
        return $accent === null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @throws Exception
     */
    public function getAccents(): ArrayIterator
    {
        return $this->getIterator();
    }
}

class phpMorphy_Dict_Lemma implements DictLemmaInterface
{
    protected $prefix_id;

    protected string $base;

    protected int $ancode_id;

    protected int $accent_id;

    protected int $flexia_id;

    /**
     * @throws Exception
     */
    public function __construct(string $base, int $flexiaId, int $accentId)
    {
        $this->base = $base;
        $this->flexia_id = $flexiaId;
        $this->accent_id = $accentId;

        if ($this->flexia_id < 0) {
            throw new Exception('flexia_id must be positive int');
        }

        if ($this->accent_id < 0) {
            throw new Exception('accent_id must be positive int');
        }
    }

    /**
     * @throws phpMorphy_Exception
     * @throws Exception
     */
    public function setPrefixId(?int $prefixId): void
    {
        if (is_null($prefixId)) {
            throw new phpMorphy_Exception('NULL id specified');
        }

        $this->prefix_id = $prefixId;

        if ($this->prefix_id < 0) {
            throw new Exception('prefix_id must be positive int');
        }
    }

    /**
     * @throws Exception
     */
    public function setAncodeId(?int $ancodeId): void
    {
        if (is_null($ancodeId)) {
            throw new Exception('NULL id specified');
        }

        $this->ancode_id = $ancodeId;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getFlexiaId(): int
    {
        return $this->flexia_id;
    }

    public function getAccentId(): int
    {
        return $this->accent_id;
    }

    public function getPrefixId(): int
    {
        return $this->prefix_id;
    }

    public function getAncodeId(): int
    {
        return $this->ancode_id;
    }

    public function hasPrefixId(): bool
    {
        return isset($this->prefix_id);
    }

    public function hasAncodeId(): bool
    {
        return isset($this->ancode_id);
    }
}
