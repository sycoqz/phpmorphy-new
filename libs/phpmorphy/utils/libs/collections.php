<?php

use Interfaces\CollectionInterface;

require_once(dirname(__FILE__) . '/iterators.php');

class phpMorphy_Collection implements CollectionInterface {
	protected $data;

	function __construct() {
		$this->clear();
	}

	function getIterator(): ArrayIterator {
		return new ArrayIterator($this->data);
	}

    /**
     * @throws phpMorphy_Exception
     */
    function import(Traversable $values): void
    {
		if(!$values instanceof Iterator) {
			throw new phpMorphy_Exception("Iterator only");
		}

		foreach($values as $v) {
			$this->append($v);
		}
	}

	function append($value): void
    {
		$this->data[] = $value;
	}

	function clear(): void
    {
		$this->data = [];
	}

	function offsetExists($offset): bool {
		return array_key_exists($offset, $this->data);
	}

    /**
     * @throws phpMorphy_Exception
     */
    function offsetGet($offset): mixed {
		if(!$this->offsetExists($offset)) {
			throw new phpMorphy_Exception("Invalid offset($offset) given");
		}

		return $this->data[$offset];
	}

	function offsetSet($offset, $value): void {
		$this->data[$offset] = $value;
	}

	function offsetUnset($offset): void {
		unset($this->data[$offset]);
	}

	function count(): int {
		return count($this->data);
	}
}

class phpMorphy_Collection_Decorator implements CollectionInterface {
	protected CollectionInterface $inner;

	function __construct(CollectionInterface $inner) {
		$this->inner = $inner;
	}

	function getIterator(): \Traversable {
		return $this->inner->getIterator();
	}

	function import(Traversable $values): void
    {
		$this->inner->import($values);
	}

	function append($value): void
    {
		$this->inner->append($value);
	}

	function clear(): void
    {
		$this->inner->clear();
	}

	function offsetExists($offset): bool {
		return $this->inner->offsetExists($offset);
	}

	function offsetGet($offset): mixed {
		return $this->inner->offsetGet($offset);
	}

	function offsetSet($offset, $value): void {
		$this->inner->offsetSet($offset, $value);
	}

	function offsetUnset($offset): void {
		$this->inner->offsetUnset($offset);
	}

	function count(): int {
		return $this->inner->count();
	}
}

class phpMorphy_Collection_Immutable extends phpMorphy_Collection_Decorator {
    /**
     * @throws phpMorphy_Exception
     */
    function append($value): void
    {
		throw new phpMorphy_Exception("Collection is immutable");
	}

    /**
     * @throws phpMorphy_Exception
     */
    function clear(): void
    {
		throw new phpMorphy_Exception("Collection is immutable");
	}

    /**
     * @throws phpMorphy_Exception
     */
    function offsetSet($offset, $value): void
    {
		throw new phpMorphy_Exception("Collection is immutable");
	}

    /**
     * @throws phpMorphy_Exception
     */
    function offsetUnset($offset): void
    {
		throw new phpMorphy_Exception("Collection is immutable");
	}
}

abstract class phpMorphy_Collection_Transform extends phpMorphy_Collection_Decorator {
	function offsetGet($offset): mixed {
		return $this->transformItem(parent::offsetGet($offset), $offset);
	}

    /**
     * @throws Exception
     */
    function getIterator(): \Traversable {
		return new phpMorphy_Iterator_TransformCallback(
			parent::getIterator(),
			array($this, 'transformItem')
		);
	}

	abstract function transformItem($item, $key);
}

class phpMorphy_Collection_Cache extends phpMorphy_Collection_Decorator {
	const UNSET_BEHAVIOUR = 1;
	const NORMAL_BEHAVIOUR = 2;

	protected array $items = [];
    protected ?int $flags = 0;

    function __construct(CollectionInterface $inner, ?int $flags = null) {
		parent::__construct($inner);

		if (isset($flags)) {
			$this->setFlags($flags);
		} else {
			$this->setFlags(self::NORMAL_BEHAVIOUR);
		}
	}

	function count(): int {
		if($this->flags & self::UNSET_BEHAVIOUR) {
			return parent::count() + count($this->items);
		} else {
			return parent::count();
		}
	}

	function setFlags($flags): void
    {
		$this->flags = $flags;
	}

	function offsetGet($offset): mixed {
		if(!isset($this->items[$offset])) {
			$this->items[$offset] = parent::offsetGet($offset);

			if($this->flags & self::UNSET_BEHAVIOUR) {
				parent::offsetUnset($offset);
			}
		}

		return $this->items[$offset];
	}
}

class phpMorphy_Collection_Typed extends phpMorphy_Collection_Decorator {
	private array $valid_types;

	function __construct(CollectionInterface $inner, $types) {
		parent::__construct($inner);

		$this->valid_types = (array)$types;
	}

    /**
     * @throws phpMorphy_Exception
     */
    function append($value): void
    {
		$this->assertType($value);
		parent::append($value);
	}

    /**
     * @throws phpMorphy_Exception
     */
    function offsetSet($offset, $value): void
    {
		$this->assertType($value);
		parent::offsetSet($offset, $value);
	}

    /**
     * @throws phpMorphy_Exception
     */
    protected function assertType($value): void
    {
		$types = $this->getType($value);

		if(count(array_intersect($types, $this->valid_types))) {
			return;
		}

		throw new phpMorphy_Exception(
			"Invalid argument type(" . implode(', ', $types) . "), [" . $this->getTypesAsString() . "] expected"
		);
	}

	protected function getType($value): array
    {
		$type = gettype($value);

		if($type === 'object') {
			$class = get_class($value);
			return array('object', strtolower($class), $class);
		} else {
			return array($type);
		}
	}

	protected function getTypesAsString(): string
    {
		return implode(', ', $this->valid_types);
	}
}
