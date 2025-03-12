<?php

interface phpMorphy_GramTab_Const_Helper_Interface
{
    public function getPartOfSpeechIdByName($name);

    public function getGrammemIdByName($name);

    public function getGrammemShiftByName($name);

    public function hasGrammemName($name);

    public function hasPartOfSpeechName($name);

    public function getGrammemsConsts();

    public function getPosesConsts();
}

class phpMorphy_GramTab_Const_Helper_Base implements phpMorphy_GramTab_Const_Helper_Interface
{
    protected $poses_map;

    protected $grammems_map;

    public function __construct($posesMap, $grammemsMap)
    {
        $this->poses_map = $posesMap;
        $this->grammems_map = $grammemsMap;
    }

    public function getGrammemsConsts()
    {
        return $this->getConsts($this->grammems_map);
    }

    public function getPosesConsts()
    {
        return $this->getConsts($this->poses_map);
    }

    protected function getConsts($map)
    {
        $result = [];

        foreach ($map as $item) {
            $result[$item['id']] = $item['const'];
        }

        return $result;
    }

    public function getPartOfSpeechIdByName($name)
    {
        $result = $this->getMapItem($this->poses_map, $name, 'part of speech');

        return $result['id'];
    }

    public function getGrammemIdByName($name)
    {
        $result = $this->getMapItem($this->grammems_map, $name, 'grammem');

        return $result['id'];
    }

    public function getGrammemShiftByName($name)
    {
        $result = $this->getMapItem($this->grammems_map, $name, 'grammem');

        return $result['shift'];
    }

    public function hasGrammemName($name)
    {
        return isset($this->grammems_map[$name]);
    }

    public function hasPartOfSpeechName($name)
    {
        return isset($this->poses_map[$name]);
    }

    protected function getMapItem($map, $name, $type)
    {
        if (isset($map[$name])) {
            return $map[$name];
        } else {
            throw new Exception("Unknown gramtab name($type) '$name' found");
        }
    }
}

class phpMorphy_GramTab_Const_Helper_ByFile extends phpMorphy_GramTab_Const_Helper_Base
{
    public function __construct($fileName)
    {
        if (false === ($xml = simplexml_load_file($fileName))) {
            throw new Exception("Can`t parse map xml file '$fileName'");
        }

        $poses = [];
        $poses_ids = [];
        foreach ($xml->part_of_speech->pos as $pos) {
            $id = (string) $pos['id'];
            $name = mb_convert_case((string) $pos['name'], MB_CASE_UPPER, 'utf-8');
            $const_name = mb_convert_case((string) $pos['const_name'], MB_CASE_UPPER, 'utf-8');

            $poses[$name] = [
                'id' => $id,
                'const' => $const_name,
            ];

            $poses_ids[] = $id;
        }

        if (count(array_unique($poses_ids)) != count($poses_ids)) {
            throw new Exception("Duplicate part of speech id found in '$fileName' file");
        }

        $grammems = [];
        $grammems_ids = [];
        foreach ($xml->grammems->grammem as $grammem) {
            $id = (string) $grammem['id'];
            $name = mb_convert_case((string) $grammem['name'], MB_CASE_UPPER, 'utf-8');
            $const_name = mb_convert_case((string) $grammem['const_name'], MB_CASE_UPPER, 'utf-8');

            $grammems[$name] = [
                'id' => $id,
                'shift' => (string) $grammem['shift'],
                'const' => $const_name,
            ];

            $grammems_ids[] = $id;
        }

        if (count(array_unique($grammems_ids)) != count($grammems_ids)) {
            throw new Exception("Duplicate grammem id found in '$fileName' file");
        }

        unset($xml);

        parent::__construct($poses, $grammems);
    }
}

class phpMorphy_GramTab_Const_Factory
{
    protected static function getLangMap()
    {
        if (false === ($map = include (dirname(__FILE__).'/lang_map.php'))) {
            throw new Exception('Can`t open langs map file');
        }

        return $map;
    }

    public static function getAllXmlFiles()
    {
        $map = self::getLangMap();

        return array_unique(array_values($map));
    }

    public static function createByXml($file)
    {
        return new phpMorphy_GramTab_Const_Helper_ByFile(dirname(__FILE__).'/'.$file);
    }

    public static function create($lang)
    {
        $map = self::getLangMap();

        $lang = strtolower($lang);
        $file = isset($map[$lang]) ? $map[$lang] : $map[false];

        return self::createByXml($file);
    }
}
