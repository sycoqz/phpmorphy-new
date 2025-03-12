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

if(!defined('PHPMORPHY_DIR')) {
    define('PHPMORPHY_DIR', dirname(__FILE__));
}

require_once(PHPMORPHY_DIR . '/fsa/fsa.php');
require_once(PHPMORPHY_DIR . '/graminfo/graminfo.php');
require_once(PHPMORPHY_DIR . '/morphiers.php');
require_once(PHPMORPHY_DIR . '/gramtab.php');
require_once(PHPMORPHY_DIR . '/storage.php');
require_once(PHPMORPHY_DIR . '/source.php');
require_once(PHPMORPHY_DIR . '/langs_stuff/common.php');

class phpMorphy_Exception extends Exception { }

// we need byte oriented string functions
// with namespaces support we only need overload string functions in current namespace
// but currently use this ugly hack.
function phpmorphy_overload_mb_funcs($prefix) {
    $GLOBALS['__phpmorphy_strlen'] = "{$prefix}strlen";
    $GLOBALS['__phpmorphy_strpos'] = "{$prefix}strpos";
    $GLOBALS['__phpmorphy_strrpos'] = "{$prefix}strrpos";
    $GLOBALS['__phpmorphy_substr'] = "{$prefix}substr";
    $GLOBALS['__phpmorphy_strtolower'] = "{$prefix}strtolower";
    $GLOBALS['__phpmorphy_strtoupper'] = "{$prefix}strtoupper";
    $GLOBALS['__phpmorphy_substr_count'] = "{$prefix}substr_count";
}

if(2 == (ini_get('mbstring.func_overload') & 2)) {
    phpmorphy_overload_mb_funcs('mb_orig_');
} else {
    phpmorphy_overload_mb_funcs('');
}

class phpMorphy_FilesBundle {
    protected
        $dir,
        $lang;

    public function __construct($dirName, $lang) {
        $this->dir = rtrim($dirName, "\\/" . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->setLang($lang);
    }

    public function getLang() {
        return $this->lang;
    }

    public function setLang($lang) {
        $this->lang = $GLOBALS['__phpmorphy_strtolower']($lang);
    }

    public function getCommonAutomatFile() {
        return $this->genFileName('common_aut');
    }

    public function getPredictAutomatFile() {
        return $this->genFileName('predict_aut');
    }

    public function getGramInfoFile() {
        return $this->genFileName('morph_data');
    }

    public function getGramInfoAncodesCacheFile() {
        return $this->genFileName('morph_data_ancodes_cache');
    }

    public function getAncodesMapFile() {
        return $this->genFileName('morph_data_ancodes_map');
    }

    public function getGramTabFile() {
        return $this->genFileName('gramtab');
    }

    public function getGramTabFileWithTextIds() {
        return $this->genFileName('gramtab_txt');
    }

    public function getDbaFile($type) {
        if(!isset($type)) {
            $type = 'db3';
        }

        return $this->genFileName("common_dict_$type");
    }

    public function getGramInfoHeaderCacheFile(): string
    {
        return $this->genFileName('morph_data_header_cache');
    }

    protected function genFileName($token, $extraExt = null): string
    {
        return $this->dir . $token . '.' . $this->lang . (isset($extraExt) ? '.' . $extraExt : '') . '.bin';
    }
};

class phpMorphy_WordDescriptor_Collection_Serializer {
    public function serialize(phpMorphy_WordDescriptor_Collection $collection, $asText): array
    {
        $result = array();

        foreach($collection as $descriptor) {
            $result[] = $this->processWordDescriptor($descriptor, $asText);
        }

        return $result;
    }

    protected function processWordDescriptor(phpMorphy_WordDescriptor $descriptor, $asText): array
    {
        $forms = array();
        $all = array();

        foreach($descriptor as $word_form) {
            $forms[] = $word_form->getWord();
            $all[] = $this->serializeGramInfo($word_form, $asText);
        }

        return array(
            'forms' => $forms,
            'all' => $all,
            'common' => '',
        );
    }

    protected function serializeGramInfo(phpMorphy_WordForm $wordForm, $asText): array|string
    {
        if($asText) {
            return $wordForm->getPartOfSpeech() . ' ' . implode(',', $wordForm->getGrammems());
        } else {
            return array(
                'pos' => $wordForm->getPartOfSpeech(),
                'grammems' => $wordForm->getGrammems()
            );
        }
    }
}

/**
 *
 */
class phpMorphy {
    const RESOLVE_ANCODES_AS_TEXT = 0;
    const RESOLVE_ANCODES_AS_DIALING = 1;
    const RESOLVE_ANCODES_AS_INT = 2;

    const NORMAL = 0;
    const IGNORE_PREDICT = 2;
    const ONLY_PREDICT = 3;

    const PREDICT_BY_NONE = 'none';
    const PREDICT_BY_SUFFIX = 'by_suffix';
    const PREDICT_BY_DB = 'by_db';

    protected
        $storage_factory,
        $common_fsa,
        $common_source,
        $predict_fsa,
        $options,

        // variables with two underscores uses lazy paradigm, i.e. initialized at first time access
        //$__common_morphier,
        //$__predict_by_suf_morphier,
        //$__predict_by_db_morphier,
        //$__bulk_morphier,
        //$__word_descriptor_serializer,

        $helper,
        $last_prediction_type
        ;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct($dir, $lang = null, $options = array()) {
        $this->options = $options = $this->repairOptions($options);

        // TODO: use two versions of phpMorphy class i.e. phpMorphy_v3 { } ... phpMorphy_v2 extends phpMorphy_v3
        if($dir instanceof phpMorphy_FilesBundle && is_array($lang)) {
            $this->initOldStyle($dir, $lang);
        } else {
            $this->initNewStyle($this->createFilesBundle($dir, $lang), $options);
        }

        $this->last_prediction_type = self::PREDICT_BY_NONE;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    public function getCommonMorphier(): phpMorphy_Morphier_Interface
    {
        return $this->__common_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    public function getPredictBySuffixMorphier(): phpMorphy_Morphier_Interface
    {
        return $this->__predict_by_suf_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    public function getPredictByDatabaseMorphier(): phpMorphy_Morphier_Interface
    {
        return $this->__predict_by_db_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Bulk
    */
    public function getBulkMorphier(): phpMorphy_Morphier_Bulk
    {
        return $this->__bulk_morphier;
    }

    /**
    * @return string
    */
    public function getEncoding(): string
    {
        return $this->helper->getGramInfo()->getEncoding();
    }

    /**
    * @return string
    */
    public function getLocale(): string
    {
        return $this->helper->getGramInfo()->getLocale();
    }

    /**
     * @return phpMorphy_GrammemsProvider_Base
     */
    public function getGrammemsProvider(): phpMorphy_GrammemsProvider_Base
    {
        return clone $this->__grammems_provider;
    }

    /**
     * @return phpMorphy_GrammemsProvider_Base
     */
    public function getDefaultGrammemsProvider(): phpMorphy_GrammemsProvider_Base
    {
        return $this->__grammems_provider;
    }

    /**
    * @return phpMorphy_Shm_Cache
    */
    public function getShmCache(): phpMorphy_Shm_Cache
    {
        return $this->storage_factory->getShmCache();
    }

    /**
    * @return bool
    */
    public function isLastPredicted(): bool
    {
        return self::PREDICT_BY_NONE !== $this->last_prediction_type;
    }

    public function getLastPredictionType(): string
    {
        return $this->last_prediction_type;
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array|phpMorphy_WordDescriptor_Collection
     */
    public function findWord(array|string $word, $type = self::NORMAL): bool|array|phpMorphy_WordDescriptor_Collection
    {
        if(is_array($word)) {
            $result = array();

            foreach($word as $w) {
                $result[$w] = $this->invoke('getWordDescriptor', $w, $type);
            }

            return $result;
        } else {
            return $this->invoke('getWordDescriptor', $word, $type);
        }
    }

    /**
     * Alias for getBaseForm
     *
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function lemmatize(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->getBaseForm($word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function getBaseForm(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getBaseForm', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function getAllForms(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getAllForms', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function getPseudoRoot(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getPseudoRoot', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function getPartOfSpeech(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getPartOfSpeech', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction management
     * @return bool|array
     */
    public function getAllFormsWithAncodes(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getAllFormsWithAncodes', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param bool $asText
     * @param mixed $type - prediction management
     * @return bool|array
     * @paradm bool $asText - represent graminfo as text or ancodes
     */
    public function getAllFormsWithGramInfo(array|string $word, $asText = true, $type = self::NORMAL): bool|array
    {
        if(false === ($result = $this->findWord($word, $type))) {
            return false;
        }

        $asText = (bool)$asText;

        if(is_array($word)) {
            $out = array();

            foreach($result as $w => $r) {
                if(false !== $r) {
                    $out[$w] = $this->processWordsCollection($r, $asText);
                } else {
                    $out[$w] = false;
                }
            }

            return $out;
        } else {
            return $this->processWordsCollection($result, $asText);
        }
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction managment
     * @return bool|array
     */
    public function getAncode(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getAncode', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction management
     * @return bool|array
     */
    public function getGramInfo(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getGrammarInfo', $word, $type);
    }

    /**
     * @param array|string $word - string or array of strings
     * @param mixed $type - prediction management
     * @return bool|array
     */
    public function getGramInfoMergeForms(array|string $word, $type = self::NORMAL): bool|array
    {
        return $this->invoke('getGrammarInfoMergeForms', $word, $type);
    }

    protected function getAnnotForWord($word, $type) {
        return $this->invoke('getAnnot', $word, $type);
    }

    /**
     * Склоняет существительное в зависимости от числа.
     *
     * @param string $word Слово для склонения.
     * @param int $number Число.
     * @return string Склоненное слово.
     */
    protected function getDeclineByNumber(string $word, int $number): string
    {
        $partOfSpeech = 'С';

        if ($number == 1) {
            $grammems = ['ИМ', 'ЕД'];
        } elseif ($number >= 2 && $number <= 4) {
            $grammems = ['РД', 'ЕД'];
        } else {
            $grammems = ['РД', 'МН'];
        }

        $result = $this->castFormByGramInfo($word, $partOfSpeech, $grammems);

        if ($result && isset($result[0]['form'])) {
            return $result[0]['form'];
        }

        return $word;
    }

    /**
     * Возвращает множественную форму слова.
     *
     * @param string $word  Слово для преобразования.
     * @return string Множественная форма слова.
     */
    public function getPluralForm(string $word): string
    {
        $partOfSpeech = 'С';

        $grammems = ['ИМ', 'МН'];

        $result = $this->castFormByGramInfo($word, $partOfSpeech, $grammems);

        if ($result && isset($result[0]['form'])) {
            return $result[0]['form'];
        }

        return $word;
    }

    /**
     * @param string $word
     * @param mixed $ancode
     * @param mixed $commonAncode
     * @param bool $returnOnlyWord
     * @param mixed $callback
     * @param mixed $type
     * @return bool|array
     */
    public function castFormByAncode(string $word, $ancode, $commonAncode = null, bool $returnOnlyWord = false, $callback = null, $type = self::NORMAL): bool|array
    {
        $resolver = $this->helper->getAncodesResolver();

        $common_ancode_id = $resolver->unresolve($commonAncode);
        $ancode_id = $resolver->unresolve($ancode);

        $data = $this->helper->getGrammemsAndPartOfSpeech($ancode_id);

        if(isset($common_ancode_id)) {
            $data[1] = array_merge($data[1], $this->helper->getGrammems($common_ancode_id));
        }

        return $this->castFormByGramInfo(
            $word,
            $data[0],
            $data[1],
            $returnOnlyWord,
            $callback,
            $type
        );
    }

    /**
     * @param string $word
     * @param mixed $partOfSpeech
     * @param array $grammems
     * @param bool $returnOnlyWord
     * @param mixed $callback
     * @param mixed $type
     * @return bool|array
     */
    public function castFormByGramInfo(string $word, mixed $partOfSpeech, array $grammems, bool $returnOnlyWord = false, $callback = null, $type = self::NORMAL): bool|array
    {
        if(false === ($annot = $this->getAnnotForWord($word, $type))) {
            return false;
        }

        return $this->helper->castFormByGramInfo($word, $annot, $partOfSpeech, $grammems, $returnOnlyWord, $callback);
    }

    /**
     * @param string $word
     * @param string $patternWord
     * @param phpMorphy_GrammemsProvider_Interface|null $grammemsProvider
     * @param bool $returnOnlyWord
     * @param mixed $callback
     * @param mixed $type
     * @return bool|array
     */
    public function castFormByPattern(string $word, string $patternWord, phpMorphy_GrammemsProvider_Interface $grammemsProvider = null, bool $returnOnlyWord = false, $callback = null, $type = self::NORMAL): bool|array
    {
        if(false === ($word_annot = $this->getAnnotForWord($word, $type))) {
            return false;
        }

        if(!isset($grammemsProvider)) {
            $grammemsProvider = $this->__grammems_provider;
        }

        $result = array();

        foreach($this->getGramInfo($patternWord, $type) as $paradigm) {
            foreach($paradigm as $grammar) {
                $pos = $grammar['pos'];

                $essential_grammems = $grammemsProvider->getGrammems($pos);

                $grammems =  false !== $essential_grammems ?
                    array_intersect($grammar['grammems'], $essential_grammems):
                    $grammar['grammems'];

                $res = $this->helper->castFormByGramInfo(
                    $word,
                    $word_annot,
                    $pos,
                    $grammems,
                    $returnOnlyWord,
                    $callback,
                    $type
                );

                if(count($res)) {
                    $result = array_merge($result, $res);
                }
            }
        }

        return $returnOnlyWord ? array_unique($result) : $result;
    }

    // public interface end

    protected function processWordsCollection(phpMorphy_WordDescriptor_Collection $collection, $asText): array
    {
        return $this->__word_descriptor_serializer->serialize($collection, $asText);
    }

    protected function invoke($method, string|array $word, $type) {
        $this->last_prediction_type = self::PREDICT_BY_NONE;

        if($type === self::ONLY_PREDICT) {
            if(is_array($word)) {
                $result = array();

                foreach($word as $w) {
                    $result[$w] = $this->predictWord($method, $w);
                }

                return $result;
            } else {
                return $this->predictWord($method, $word);
            }
        }

        if(is_array($word)) {
            $result = $this->__bulk_morphier->$method($word);

            if($type !== self::IGNORE_PREDICT) {
                $not_found = $this->__bulk_morphier->getNotFoundWords();

                for($i = 0, $c = count($not_found); $i < $c; $i++) {
                    $word = $not_found[$i];

                    $result[$word] = $this->predictWord($method, $word);
                }
            } else {
                for($i = 0, $c = count($not_found); $i < $c; $i++) {
                    $result[$not_found[$i]] = false;
                }
            }

        } else {
            if(false === ($result = $this->__common_morphier->$method($word))) {
                if($type !== self::IGNORE_PREDICT) {
                    return $this->predictWord($method, $word);
                }
            }

        }
        return $result;
    }

    protected function predictWord($method, $word) {
        if(false !== ($result = $this->__predict_by_suf_morphier->$method($word))) {
            $this->last_prediction_type = self::PREDICT_BY_SUFFIX;

            return $result;
        }

        if(false !== ($result = $this->__predict_by_db_morphier->$method($word))) {
            $this->last_prediction_type = self::PREDICT_BY_DB;

            return $result;
        }

        return false;
    }

    ////////////////
    // init code
    ////////////////
    /**
     * @throws phpMorphy_Exception
     */
    protected function initNewStyle(phpMorphy_FilesBundle $bundle, $options): void
    {
        $this->options = $options = $this->repairOptions($options);
        $storage_type = $options['storage'];

        $storage_factory = $this->storage_factory = $this->createStorageFactory($options['shm']);
        $graminfo_as_text = $this->options['graminfo_as_text'];

        // fsa
        $this->common_fsa = $this->createFsa($storage_factory->open($storage_type, $bundle->getCommonAutomatFile(), false), false); // lazy
        $this->predict_fsa = $this->createFsa($storage_factory->open($storage_type, $bundle->getPredictAutomatFile(), true), true);  // lazy

        // graminfo
        $graminfo = $this->createGramInfo($storage_factory->open($storage_type, $bundle->getGramInfoFile(), true), $bundle); // lazy

        // gramtab
        $gramtab = $this->createGramTab(
            $storage_factory->open(
                $storage_type,
                $graminfo_as_text ? $bundle->getGramTabFileWithTextIds() : $bundle->getGramTabFile(),
                true
            )
        ); // always lazy

        // common source
        //$this->__common_source = $this->createCommonSource($bundle, $this->options['common_source']);

        $this->helper = $this->createMorphierHelper($graminfo, $gramtab, $graminfo_as_text, $bundle);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createCommonSource(phpMorphy_FilesBundle $bundle, $opts): phpMorphy_Source_Fsa|phpMorphy_Source_Dba
    {
        $type = $opts['type'];

        return match ($type) {
            PHPMORPHY_SOURCE_FSA => new phpMorphy_Source_Fsa($this->common_fsa),
            PHPMORPHY_SOURCE_DBA => new phpMorphy_Source_Dba(
                $bundle->getDbaFile($this->getDbaHandlerName(@$opts['opts']['handler'])),
                $opts['opts']
            ),
            default => throw new phpMorphy_Exception("Unknown source type given '$type'"),
        };
    }

    protected function getDbaHandlerName($name): string
    {
        return $name ?? phpMorphy_Source_Dba::getDefaultHandler();
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function initOldStyle(phpMorphy_FilesBundle $bundle, $options): void
    {
        $options = $this->repairOptions($options);

        switch($bundle->getLang()) {
            case 'rus':
                $bundle->setLang('ru_RU');
                break;
            case 'eng':
                $bundle->setLang('en_EN');
                break;
            case 'ger':
                $bundle->setLang('de_DE');
                break;
        }

        $this->initNewStyle($bundle, $options);
    }

    protected function repairOldOptions($options): array
    {
        $defaults = array(
            'predict_by_suffix' => false,
            'predict_by_db' => false,
        );

        return (array)$options + $defaults;
    }

    protected function repairSourceOptions($options): array
    {
        $defaults = array(
            'type' => PHPMORPHY_SOURCE_FSA,
            'opts' => null
        );

        return (array)$options + $defaults;
    }

    protected function repairOptions($options): array
    {
        $defaults = array(
            'shm' => array(),
            'graminfo_as_text' => true,
            'storage' => PHPMORPHY_STORAGE_FILE,
            'common_source' => $this->repairSourceOptions($options['common_source'] ?? ''),
            'predict_by_suffix' => true,
            'predict_by_db' => true,
            'use_ancodes_cache' => false,
            'resolve_ancodes' => self::RESOLVE_ANCODES_AS_TEXT
        );

        return (array)$options + $defaults;
    }

    private array $dynamicInstances = [];

    /**
     * @throws phpMorphy_Exception
     */
    public function __get($name) {
        if (isset($this->dynamicInstances[$name])) {
            return $this->dynamicInstances[$name];
        }
        $v = match ($name) {
            '__predict_by_db_morphier' => $this->createPredictByDbMorphier(
                $this->predict_fsa,
                $this->helper
            ),
            '__predict_by_suf_morphier' => $this->createPredictBySuffixMorphier(
                $this->common_fsa,
                $this->helper
            ),
            '__bulk_morphier' => $this->createBulkMorphier(
                $this->common_fsa,
                $this->helper
            ),
            '__common_morphier' => $this->createCommonMorphier(
                $this->common_fsa,
                $this->helper
            ),
            '__word_descriptor_serializer' => $this->createWordDescriptorSerializer(),
            '__grammems_provider' => $this->createGrammemsProvider(),
            default => throw new phpMorphy_Exception("Invalid prop name '$name'"),
        };

        $this->dynamicInstances[$name] = $v;
        return $v;
    }

    ////////////////////
    // factory methods
    ////////////////////
    /**
     * @throws phpMorphy_Exception
     */
    public function createGrammemsProvider() {
        return phpMorphy_GrammemsProvider_Factory::create($this);
    }

    protected function createWordDescriptorSerializer(): phpMorphy_WordDescriptor_Collection_Serializer
    {
        return new phpMorphy_WordDescriptor_Collection_Serializer();
    }

    protected function createFilesBundle($dir, $lang): phpMorphy_FilesBundle
    {
        return new phpMorphy_FilesBundle($dir, $lang);
    }

    protected function createStorageFactory($options): phpMorphy_Storage_Factory
    {
        return new phpMorphy_Storage_Factory($options);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createFsa(phpMorphy_Storage $storage, $lazy) {
        return phpMorphy_Fsa::create($storage, $lazy);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createGramInfo(phpMorphy_Storage $graminfoFile, phpMorphy_FilesBundle $bundle): phpMorphy_GramInfo_RuntimeCaching|phpMorphy_GramInfo_AncodeCache
    {
        //return new phpMorphy_GramInfo_RuntimeCaching(new phpMorphy_GramInfo_Proxy($storage));
        //return new phpMorphy_GramInfo_RuntimeCaching(phpMorphy_GramInfo::create($storage, false));

        $result = new phpMorphy_GramInfo_RuntimeCaching(
            new phpMorphy_GramInfo_Proxy_WithHeader(
                $graminfoFile,
                $bundle->getGramInfoHeaderCacheFile()
            )
        );

        if($this->options['use_ancodes_cache']) {
            return new phpMorphy_GramInfo_AncodeCache(
                $result,
                $this->storage_factory->open(
                    $this->options['storage'],
                    $bundle->getGramInfoAncodesCacheFile(),
                    true
                ) // always lazy open
            );
        } else {
            return $result;
        }
    }

    protected function createGramTab(phpMorphy_Storage $storage): phpMorphy_GramTab_Proxy
    {
        return new phpMorphy_GramTab_Proxy($storage);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createAncodesResolverInternal(phpMorphy_GramTab_Interface $gramtab, phpMorphy_FilesBundle $bundle): array
    {
        return match ($this->options['resolve_ancodes']) {
            self::RESOLVE_ANCODES_AS_TEXT => array(
                'phpMorphy_AncodesResolver_ToText',
                array($gramtab)
            ),
            self::RESOLVE_ANCODES_AS_INT => array(
                'phpMorphy_AncodesResolver_AsIs',
                array()
            ),
            self::RESOLVE_ANCODES_AS_DIALING => array(
                'phpMorphy_AncodesResolver_ToDialingAncodes',
                array(
                    $this->storage_factory->open(
                        $this->options['storage'],
                        $bundle->getAncodesMapFile(),
                        true
                    ) // always lazy open
                )
            ),
            default => throw new phpMorphy_Exception("Invalid resolve_ancodes option, valid values are RESOLVE_ANCODES_AS_DIALING, RESOLVE_ANCODES_AS_INT, RESOLVE_ANCODES_AS_TEXT"),
        };
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createAncodesResolver(phpMorphy_GramTab_Interface $gramtab, phpMorphy_FilesBundle $bundle, $lazy) {
        $result = $this->createAncodesResolverInternal($gramtab, $bundle);

        if($lazy) {
            return new phpMorphy_AncodesResolver_Proxy($result[0], $result[1]);
        } else {
            return phpMorphy_AncodesResolver_Proxy::instantinate($result[0], $result[1]);
        }
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function createMorphierHelper(
        phpMorphy_GramInfo_Interace $graminfo,
        phpMorphy_GramTab_Interface $gramtab,
        $graminfoAsText,
        phpMorphy_FilesBundle $bundle
    ): phpMorphy_Morphier_Helper
    {
        return new phpMorphy_Morphier_Helper(
            $graminfo,
            $gramtab,
            $this->createAncodesResolver($gramtab, $bundle, true),
            $graminfoAsText
        );
    }

    protected function createCommonMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper): phpMorphy_Morphier_Common
    {
        return new phpMorphy_Morphier_Common($fsa, $helper);
    }

    protected function createBulkMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper): phpMorphy_Morphier_Bulk
    {
        return new phpMorphy_Morphier_Bulk($fsa, $helper);
    }

    protected function createPredictByDbMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper): phpMorphy_Morphier_Empty|phpMorphy_Morphier_Predict_Database
    {
        if($this->options['predict_by_db']) {
                return new phpMorphy_Morphier_Predict_Database($fsa, $helper);
        } else {
            return new phpMorphy_Morphier_Empty();
        }
    }

    protected function createPredictBySuffixMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper): phpMorphy_Morphier_Predict_Suffix|phpMorphy_Morphier_Empty
    {
        if($this->options['predict_by_suffix']) {
            return new phpMorphy_Morphier_Predict_Suffix($fsa, $helper);
        } else {
            return new phpMorphy_Morphier_Empty();
        }
    }
};
