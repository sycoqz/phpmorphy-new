<?php

use Interfaces\FsaInterface;

class phpMorphy_Link_Base
{
    protected $trans;

    protected $raw_trans;

    protected FsaInterface $fsa;

    public function __construct(FsaInterface $fsa, $trans, $rawTrans)
    {
        $this->fsa = $fsa;
        $this->trans = $trans;
        $this->raw_trans = $rawTrans;
    }

    public function isAnnotation() {}

    public function getTrans()
    {
        return $this->trans;
    }

    public function getFsa(): FsaInterface
    {
        return $this->fsa;
    }

    public function getRawTrans()
    {
        return $this->raw_trans;
    }
}

/**
 * This class represent "normal" link i.e. link that points to automat state
 */
class phpMorphy_Link extends phpMorphy_Link_Base
{
    public function isAnnotation(): bool
    {
        return false;
    }

    public function getDest()
    {
        return $this->trans['dest'];
    }

    public function getAttr()
    {
        return $this->trans['attr'];
    }

    public function getTargetState(): phpMorphy_State
    {
        return $this->createState($this->trans['dest']);
    }

    protected function createState($index): phpMorphy_State
    {
        return new phpMorphy_State($this->fsa, $index);
    }
}

class phpMorphy_Link_Annot extends phpMorphy_Link_Base
{
    public function isAnnotation(): bool
    {
        return true;
    }

    public function getAnnotation(): string
    {
        return $this->fsa->getAnnot($this->raw_trans);
    }
}

class phpMorphy_State
{
    protected array $raw_transes;

    protected array $transes;

    protected FsaInterface $fsa;

    public function __construct(FsaInterface $fsa, $index)
    {
        $this->fsa = $fsa;

        $this->raw_transes = $fsa->readState($index);
        $this->transes = $fsa->unpackTranses($this->raw_transes);
    }

    public function getLinks(): array
    {
        $result = [];

        for ($i = 0, $c = count($this->transes); $i < $c; $i++) {
            $trans = $this->transes[$i];

            if (! $trans['term']) {
                $result[] = $this->createNormalLink($trans, $this->raw_transes[$i]);
            } else {
                $result[] = $this->createAnnotLink($trans, $this->raw_transes[$i]);
            }
        }

        return $result;
    }

    public function getSize(): int
    {
        return count($this->transes);
    }

    protected function createNormalLink($trans, $raw): phpMorphy_Link
    {
        return new phpMorphy_Link($this->fsa, $trans, $raw);
    }

    protected function createAnnotLink($trans, $raw): phpMorphy_Link_Annot
    {
        return new phpMorphy_Link_Annot($this->fsa, $trans, $raw);
    }
}
