<?php

require_once dirname(__FILE__).'/iterators.php';

interface phpMorphy_Collection_Interface extends ArrayAccess, Countable, IteratorAggregate
{
    public function import(Traversable $values);

    public function append($value);

    public function clear();
}

class phpMorphy_Collection implements phpMorphy_Collection_Interface
{
    protected $data;

    public function __construct()
    {
        $this->clear();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    public function import(Traversable $values)
    {
        if (! $values instanceof Iterator) {
            throw new phpMorphy_Exception('Iterator only');
        }

        foreach ($values as $v) {
            $this->append($v);
        }
    }

    public function append($value)
    {
        $this->data[] = $value;
    }

    public function clear()
    {
        $this->data = [];
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset): mixed
    {
        if (! $this->offsetExists($offset)) {
            throw new phpMorphy_Exception("Invalid offset($offset) given");
        }

        return $this->data[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function count(): int
    {
        return count($this->data);
    }
}

class phpMorphy_Collection_Decorator implements phpMorphy_Collection_Interface
{
    protected $inner;

    public function __construct(phpMorphy_Collection_Interface $inner)
    {
        $this->inner = $inner;
    }

    public function getIterator(): \Traversable
    {
        return $this->inner->getIterator();
    }

    public function import(Traversable $values)
    {
        $this->inner->import($values);
    }

    public function append($value)
    {
        $this->inner->append($value);
    }

    public function clear()
    {
        $this->inner->clear();
    }

    public function offsetExists($offset): bool
    {
        return $this->inner->offsetExists($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->inner->offsetGet($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->inner->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->inner->offsetUnset($offset);
    }

    public function count(): int
    {
        return $this->inner->count();
    }
}

class phpMorphy_Collection_Immutable extends phpMorphy_Collection_Decorator
{
    public function append($value)
    {
        throw new phpMorphy_Exception('Collection is immutable');
    }

    public function clear()
    {
        throw new phpMorphy_Exception('Collection is immutable');
    }

    public function offsetSet($offset, $value)
    {
        throw new phpMorphy_Exception('Collection is immutable');
    }

    public function offsetUnset($offset)
    {
        throw new phpMorphy_Exception('Collection is immutable');
    }
}

abstract class phpMorphy_Collection_Transform extends phpMorphy_Collection_Decorator
{
    public function offsetGet($offset): mixed
    {
        return $this->transformItem(parent::offsetGet($offset), $offset);
    }

    public function getIterator(): \Traversable
    {
        return new phpMorphy_Iterator_TransformCallback(
            parent::getIterator(),
            [$this, 'transformItem']
        );
    }

    abstract public function transformItem($item, $key);
}

class phpMorphy_Collection_Cache extends phpMorphy_Collection_Decorator
{
    const UNSET_BEHAVIOUR = 1;

    const NORMAL_BEHAVIOUR = 2;

    protected $flags = 0;

    protected $items = [];

    public function __construct(phpMorphy_Collection_Interface $inner, $flags = null)
    {
        parent::__construct($inner);

        if (isset($flags)) {
            $this->setFlags($flags);
        } else {
            $this->setFlags(self::NORMAL_BEHAVIOUR);
        }
    }

    public function count(): int
    {
        if ($this->flags & self::UNSET_BEHAVIOUR) {
            return parent::count() + count($this->items);
        } else {
            return parent::count();
        }
    }

    public function setFlags($flags)
    {
        $this->flags = $flags;
    }

    public function offsetGet($offset): mixed
    {
        if (! isset($this->items[$offset])) {
            $this->items[$offset] = parent::offsetGet($offset);

            if ($this->flags & self::UNSET_BEHAVIOUR) {
                parent::offsetUnset($offset);
            }
        }

        return $this->items[$offset];
    }
}

class phpMorphy_Collection_Typed extends phpMorphy_Collection_Decorator
{
    private $valid_types;

    public function __construct(phpMorphy_Collection_Interface $inner, $types)
    {
        parent::__construct($inner);

        $this->valid_types = (array) $types;
    }

    public function append($value)
    {
        $this->assertType($value);
        parent::append($value);
    }

    public function offsetSet($offset, $value)
    {
        $this->assertType($value);
        parent::offsetSet($offset, $value);
    }

    protected function assertType($value)
    {
        $types = $this->getType($value);

        if (count(array_intersect($types, $this->valid_types))) {
            return;
        }

        throw new phpMorphy_Exception(
            'Invalid argument type('.implode(', ', $types).'), ['.$this->getTypesAsString().'] expected'
        );
    }

    protected function getType($value)
    {
        $type = gettype($value);

        if ($type === 'object') {
            $class = get_class($value);

            return ['object', strtolower($class), $class];
        } else {
            return [$type];
        }
    }

    protected function getTypesAsString()
    {
        return implode(', ', $this->valid_types);
    }
}
