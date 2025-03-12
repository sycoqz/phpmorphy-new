<?php

use Interfaces\DictFlexiaInterface;
use Interfaces\DictSourceInterface;

require_once dirname(__FILE__).'/source.php';
require_once dirname(__FILE__).'/utils/gramtab/helper.php';
require_once dirname(__FILE__).'/../../../libs/decorator.php';

class phpMorphy_Dict_Ancode_Normalized
{
    private int $id;

    private string $name;

    private int $pos_id;

    private array $grammems_ids;

    public function __construct(int $id, string $name, int $posId, array $grammemsIds)
    {
        $this->id = $id;
        $this->pos_id = $posId;
        $this->grammems_ids = array_map('intval', $grammemsIds);
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPartOfSpeechId(): int
    {
        return $this->pos_id;
    }

    public function getGrammemsIds(): array
    {
        return $this->grammems_ids;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class phpMorphy_Dict_PartOfSpeech
{
    private int $id;

    private string $name;

    private bool $is_predict;

    public function __construct(int $id, string $name, bool $isPredict)
    {
        $this->id = $id;
        $this->is_predict = $isPredict;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isPredict(): bool
    {
        return $this->is_predict;
    }
}

class phpMorphy_Dict_Grammem
{
    private int $id;

    private string $name;

    private int $shift;

    public function __construct(int $id, string $name, int $shift)
    {
        $this->id = $id;
        $this->shift = $shift;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getShift(): int
    {
        return $this->shift;
    }
}

lmbDecorator::generate('phpMorphy_Dict_FlexiaModel', 'phpMorphy_Dict_FlexiaModel_Decorator');

class phpMorphy_Dict_FlexiaModel_Normalized extends phpMorphy_Dict_FlexiaModel_Decorator
{
    protected phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager;

    public function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_FlexiaModel $inner)
    {
        parent::__construct($inner);
        $this->manager = $manager;
    }

    /**
     * @throws Exception
     */
    public function getIterator(): \Traversable
    {
        return new phpMorphy_Iterator_TransformCallback(
            parent::getIterator(),
            [$this, '__decorate'],
            phpMorphy_Iterator_TransformCallback::CALL_WITHOUT_KEY
        );
    }

    public function offsetGet($offset): mixed
    {
        return $this->decorate(parent::offsetGet($offset));
    }

    public function __decorate(DictFlexiaInterface $flexia): phpMorphy_Dict_Flexia_Normalized
    {
        return new phpMorphy_Dict_Flexia_Normalized($this->manager, $flexia);
    }
}

lmbDecorator::generate('DictFlexiaInterface', 'phpMorphy_Dict_Flexia_Decorator');

// Decorator over flexia
class phpMorphy_Dict_Flexia_Normalized extends phpMorphy_Dict_Flexia_Decorator
{
    protected $manager;

    public function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_Flexia $inner)
    {
        parent::__construct($inner);
        $this->manager = $manager;
    }

    /**
     * @throws Exception
     */
    public function getAncodeId()
    {
        return $this->manager->resolveAncode(parent::getAncodeId());
    }
}

lmbDecorator::generate('DictLemmaInterface', 'phpMorphy_Dict_Lemma_Decorator');

// Decorator over lemma
class phpMorphy_Dict_Lemma_Normalized extends phpMorphy_Dict_Lemma_Decorator
{
    protected phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager;

    public function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_Lemma $inner)
    {
        parent::__construct($inner);
        $this->manager = $manager;
    }

    /**
     * @throws Exception
     */
    public function getAncodeId()
    {
        return $this->manager->resolveAncode(parent::getAncodeId());
    }
}

class phpMorphy_Dict_Source_Normalized_DecoratingIterator extends IteratorIterator
{
    protected phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager;

    protected $new_class;

    public function __construct(Traversable $it, phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, $newClass)
    {
        parent::__construct($it);

        $this->manager = $manager;
        $this->new_class = $newClass;
    }

    public function current(): mixed
    {
        return $this->decorate(parent::current());
    }

    protected function decorate($object)
    {
        $new_class = $this->new_class;

        return new $new_class($this->manager, $object);
    }
}

class phpMorphy_Dict_Source_Normalized_Ancodes_Manager
{
    private array $ancodes_map = [];

    private array $poses_map = [];

    private array $grammems_map = [];

    private array $ancodes = [];

    private phpMorphy_GramTab_Const_Helper_ByFile $helper;

    public function __construct(DictSourceInterface $source)
    {
        $this->helper = phpMorphy_GramTab_Const_Factory::create($source->getLanguage());

        foreach ($source->getAncodes() as $ancode) {
            $this->ancodes[] = $this->createAncode($ancode);
        }
    }

    protected function registerAncodeId($ancodeId)
    {
        if (! isset($this->ancodes_map[$ancodeId])) {
            $new_id = count($this->ancodes_map);

            $this->ancodes_map[$ancodeId] = $new_id;
        }

        return $this->ancodes_map[$ancodeId];
    }

    protected function registerPos($pos, $isPredict): int
    {
        $pos = mb_convert_case($pos, MB_CASE_UPPER, 'utf-8');

        if (! isset($this->poses_map[$pos])) {
            $pos_id = $this->helper->getPartOfSpeechIdByName($pos);

            $this->poses_map[$pos] = $this->createPos($pos_id, $pos, $isPredict);
        }

        return $this->poses_map[$pos]->getId();
    }

    protected function createPos($id, $name, $isPredict): phpMorphy_Dict_PartOfSpeech
    {
        return new phpMorphy_Dict_PartOfSpeech($id, $name, $isPredict);
    }

    protected function createGrammem($id, $name, $shift): phpMorphy_Dict_Grammem
    {
        return new phpMorphy_Dict_Grammem($id, $name, $shift);
    }

    protected function registerGrammems(Traversable $it): array
    {
        $result = [];

        foreach ($it as $grammem) {
            $grammem = mb_convert_case($grammem, MB_CASE_UPPER, 'utf-8');

            if (! isset($this->grammems_map[$grammem])) {
                $grammem_id = $this->helper->getGrammemIdByName($grammem);
                $shift = $this->helper->getGrammemShiftByName($grammem);

                $this->grammems_map[$grammem] = $this->createGrammem($grammem_id, $grammem, $shift);
            }

            $result[] = $this->grammems_map[$grammem]->getId();
        }

        return $result;
    }

    public function getAncodesMap()
    {
        return $this->ancodes_map;
    }

    public function getPosesMap(): array
    {
        return $this->poses_map;
    }

    public function getGrammemsMap(): array
    {
        return $this->grammems_map;
    }

    /**
     * @throws Exception
     */
    public function resolveAncode($ancodeId)
    {
        if (! isset($this->ancodes_map[$ancodeId])) {
            throw new Exception("Unknown ancode_id '$ancodeId' given");
        }

        return $this->ancodes_map[$ancodeId];
    }

    public function getAncodes()
    {
        return $this->ancodes;
    }

    public function getAncode($ancodeId, $resolve = true)
    {
        $ancode_id = $resolve ? $this->resolveAncode($ancodeId) : (int) $ancodeId;

        return $this->ancodes[$ancode_id];
    }

    protected function createAncode(phpMorphy_Dict_Ancode $ancode): phpMorphy_Dict_Ancode_Normalized
    {
        return new phpMorphy_Dict_Ancode_Normalized(
            $this->registerAncodeId($ancode->getId()),
            $ancode->getId(),
            $this->registerPos($ancode->getPartOfSpeech(), $ancode->isPredict()),
            $this->registerGrammems($ancode->getGrammems()),
        );
    }
}

lmbDecorator::generate('DictSourceInterface', 'phpMorphy_Dict_Source_Normalized_Decorator');

class phpMorphy_Dict_Source_Normalized_Ancodes extends phpMorphy_Dict_Source_Normalized_Decorator
{
    protected $manager;

    public static function wrap(DictSourceInterface $source)
    {
        $self = __CLASS__;

        if ($source instanceof $self) {
            return $source;
        }

        return new $self($source);
    }

    public function __construct(DictSourceInterface $inner)
    {
        parent::__construct($inner);

        $this->manager = $this->createManager($inner);
    }

    protected function createManager($inner): phpMorphy_Dict_Source_Normalized_Ancodes_Manager
    {
        return new phpMorphy_Dict_Source_Normalized_Ancodes_Manager($inner);
    }

    public function getPoses(): array
    {
        return array_values($this->manager->getPosesMap());
    }

    public function getGrammems(): array
    {
        return array_values($this->manager->getGrammemsMap());
    }

    public function getAncodesNormalized(): array
    {
        return $this->manager->getAncodes();
    }

    public function getFlexiasNormalized(): phpMorphy_Dict_Source_Normalized_DecoratingIterator
    {
        return $this->createDecoratingIterator($this->getFlexias(), 'phpMorphy_Dict_FlexiaModel_Normalized');
    }

    public function getLemmasNormalized(): phpMorphy_Dict_Source_Normalized_DecoratingIterator
    {
        return $this->createDecoratingIterator($this->getLemmas(), 'phpMorphy_Dict_Lemma_Normalized');
    }

    protected function createDecoratingIterator(Traversable $it, $newClass): phpMorphy_Dict_Source_Normalized_DecoratingIterator
    {
        return new phpMorphy_Dict_Source_Normalized_DecoratingIterator($it, $this->manager, $newClass);
    }
}
