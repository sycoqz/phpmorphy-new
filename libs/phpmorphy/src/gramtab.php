<?php

/**
 * This file is part of phpMorphy library
 *
 * Copyright c 2007-2008 Kamaev Vladimir <heromantor@users.sourceforge.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the
 * Free Software Foundation, Inc., 59 Temple Place - Suite 330,
 * Boston, MA 02111-1307, USA.
 */
interface phpMorphy_GramTab_Interface
{
    public function getGrammems($ancodeId);

    public function getPartOfSpeech($ancodeId);

    public function resolveGrammemIds($ids);

    public function resolvePartOfSpeechId($id);

    public function includeConsts();

    public function ancodeToString($ancodeId, $commonAncode = null);

    public function stringToAncode($string);

    public function toString($partOfSpeechId, $grammemIds);
}

class phpMorphy_GramTab_Empty implements phpMorphy_GramTab_Interface
{
    public function getGrammems($ancodeId)
    {
        return [];
    }

    public function getPartOfSpeech($ancodeId)
    {
        return 0;
    }

    public function resolveGrammemIds($ids)
    {
        return is_array($ids) ? [] : '';
    }

    public function resolvePartOfSpeechId($id)
    {
        return '';
    }

    public function includeConsts() {}

    public function ancodeToString($ancodeId, $commonAncode = null)
    {
        return '';
    }

    public function stringToAncode($string)
    {
        return null;
    }

    public function toString($partOfSpeechId, $grammemIds)
    {
        return '';
    }
}

class phpMorphy_GramTab_Proxy implements phpMorphy_GramTab_Interface
{
    protected $storage;

    protected $__obj;

    public function __construct(phpMorphy_Storage $storage)
    {
        $this->storage = $storage;
    }

    public function getGrammems($ancodeId)
    {
        return $this->getObj()->getGrammems($ancodeId);
    }

    public function getPartOfSpeech($ancodeId)
    {
        return $this->getObj()->getPartOfSpeech($ancodeId);
    }

    public function resolveGrammemIds($ids)
    {
        return $this->getObj()->resolveGrammemIds($ids);
    }

    public function resolvePartOfSpeechId($id)
    {
        return $this->getObj()->resolvePartOfSpeechId($id);
    }

    public function includeConsts()
    {
        return $this->getObj()->includeConsts();
    }

    public function ancodeToString($ancodeId, $commonAncode = null)
    {
        return $this->getObj()->ancodeToString($ancodeId, $commonAncode);
    }

    public function stringToAncode($string)
    {
        return $this->getObj()->stringToAncode($string);
    }

    public function toString($partOfSpeechId, $grammemIds)
    {
        return $this->getObj()->toString($partOfSpeechId, $grammemIds);
    }

    public function getObj()
    {
        if ($this->__obj !== null) {
            return $this->__obj;
        }
        $this->__obj = phpMorphy_GramTab::create($this->storage);
        unset($this->storage);

        return $this->__obj;
    }
}

class phpMorphy_GramTab implements phpMorphy_GramTab_Interface
{
    protected $data;

    protected $ancodes;

    protected $grammems;

    protected $__ancodes_map;

    protected $poses;

    protected function __construct(phpMorphy_Storage $storage)
    {
        $this->data = unserialize($storage->read(0, $storage->getFileSize()));

        if ($this->data === false) {
            throw new phpMorphy_Exception('Broken gramtab data');
        }

        $this->grammems = $this->data['grammems'];
        $this->poses = $this->data['poses'];
        $this->ancodes = $this->data['ancodes'];
    }

    // TODO: remove this
    public static function create(phpMorphy_Storage $storage)
    {
        return new phpMorphy_GramTab($storage);
    }

    public function getGrammems($ancodeId)
    {
        if (! isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }

        return $this->ancodes[$ancodeId]['grammem_ids'];
    }

    public function getPartOfSpeech($ancodeId)
    {
        if (! isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }

        return $this->ancodes[$ancodeId]['pos_id'];
    }

    public function resolveGrammemIds($ids)
    {
        if (is_array($ids)) {
            $result = [];

            foreach ($ids as $id) {
                if (! isset($this->grammems[$id])) {
                    throw new phpMorphy_Exception("Invalid grammem id '$id'");
                }

                $result[] = $this->grammems[$id]['name'];
            }

            return $result;
        } else {
            if (! isset($this->grammems[$ids])) {
                throw new phpMorphy_Exception("Invalid grammem id '$ids'");
            }

            return $this->grammems[$ids]['name'];
        }
    }

    public function resolvePartOfSpeechId($id)
    {
        if (! isset($this->poses[$id])) {
            throw new phpMorphy_Exception("Invalid part of speech id '$id'");
        }

        return $this->poses[$id]['name'];
    }

    public function includeConsts()
    {
        require_once PHPMORPHY_DIR.'/gramtab_consts.php';
    }

    public function ancodeToString($ancodeId, $commonAncode = null)
    {
        if (isset($commonAncode)) {
            $commonAncode = implode(',', $this->getGrammems($commonAncode)).',';
        }

        return
            $this->getPartOfSpeech($ancodeId).' '.
            $commonAncode.
            implode(',', $this->getGrammems($ancodeId));
    }

    protected function findAncode($partOfSpeech, $grammems) {}

    public function stringToAncode($string)
    {
        if (! isset($string)) {
            return null;
        }

        if ($this->__ancodes_map === null) {
            $this->__ancodes_map = $this->buildAncodesMap();
        }

        if (! isset($this->__ancodes_map[$string])) {
            throw new phpMorphy_Exception("Ancode with '$string' graminfo not found");
        }

        return $this->__ancodes_map[$string];
    }

    public function toString($partOfSpeechId, $grammemIds)
    {
        return $partOfSpeechId.' '.implode(',', $grammemIds);
    }

    protected function buildAncodesMap()
    {
        $result = [];

        foreach ($this->ancodes as $ancode_id => $data) {
            $key = $this->toString($data['pos_id'], $data['grammem_ids']);

            $result[$key] = $ancode_id;
        }

        return $result;
    }
}
