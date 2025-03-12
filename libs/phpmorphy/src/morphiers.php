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

use enums\Grammems;
use enums\PartOfSpeech;
use Interfaces\AncodesResolverInterface;
use Interfaces\AnnotDecoderInterface;
use Interfaces\FsaInterface;
use Interfaces\GramTabInterface;
use Interfaces\MorphierFinderInterface;
use Interfaces\MorphierInterface;

require_once PHPMORPHY_DIR.'/gramtab.php';
require_once PHPMORPHY_DIR.'/unicode.php';

class phpMorphy_Morphier_Empty implements MorphierInterface
{
    public function getAnnot($word): bool
    {
        return false;
    }

    public function getBaseForm($word): bool
    {
        return false;
    }

    public function getAllForms($word): bool
    {
        return false;
    }

    public function getAllFormsWithGramInfo($word): bool
    {
        return false;
    }

    public function getPseudoRoot($word): bool
    {
        return false;
    }

    public function getPartOfSpeech($word): bool
    {
        return false;
    }

    public function getWordDescriptor($word): bool
    {
        return false;
    }

    public function getAllFormsWithAncodes($word): bool
    {
        return false;
    }

    public function getAncode($word): bool
    {
        return false;
    }

    public function getGrammarInfoMergeForms($word): bool
    {
        return false;
    }

    public function getGrammarInfo($word): bool
    {
        return false;
    }

    public function castFormByGramInfo($word, $partOfSpeech, $grammems, $returnWords = false, $callback = null): bool|array
    {
        return false;
    }
}

// ----------------------------
// Annot decoder
// ----------------------------

abstract class phpMorphy_AnnotDecoder_Base implements AnnotDecoderInterface
{
    const INVALID_ANCODE_ID = 0xFFFF;

    protected $ends;

    protected $unpack_str;

    protected $block_size;

    public function __construct($ends)
    {
        $this->ends = $ends;

        $this->unpack_str = $this->getUnpackString();
        $this->block_size = $this->getUnpackBlockSize();
    }

    abstract protected function getUnpackString();

    abstract protected function getUnpackBlockSize();

    /**
     * @throws phpMorphy_Exception
     */
    public function decode($annotRaw, $withBase)
    {
        if (empty($annotRaw)) {
            throw new phpMorphy_Exception('Empty annot given');
        }

        $unpack_str = $this->unpack_str;
        $unpack_size = $this->block_size;

        $result = unpack("Vcount/$unpack_str", $annotRaw);

        if ($result === false) {
            throw new phpMorphy_Exception("Invalid annot string '$annotRaw'");
        }

        if ($result['common_ancode'] == self::INVALID_ANCODE_ID) {
            $result['common_ancode'] = null;
        }

        $count = $result['count'];

        $result = [$result];

        if ($count > 1) {
            for ($i = 0; $i < $count - 1; $i++) {
                $res = unpack($unpack_str, $GLOBALS['__phpmorphy_substr']($annotRaw, 4 + ($i + 1) * $unpack_size, $unpack_size));

                if ($res['common_ancode'] == self::INVALID_ANCODE_ID) {
                    $res['common_ancode'] = null;
                }

                $result[] = $res;
            }
        }

        if ($withBase) {
            $items = explode($this->ends, $GLOBALS['__phpmorphy_substr']($annotRaw, 4 + $count * $unpack_size));
            for ($i = 0; $i < $count; $i++) {
                $result[$i]['base_prefix'] = $items[$i * 2];
                $result[$i]['base_suffix'] = $items[$i * 2 + 1];
            }
        }

        return $result;
    }
}

class phpMorphy_AnnotDecoder_Common extends phpMorphy_AnnotDecoder_Base
{
    protected function getUnpackString(): string
    {
        return 'Voffset/vcplen/vplen/vflen/vcommon_ancode/vforms_count/vpacked_forms_count/vaffixes_size/vform_no/vpos_id';
    }

    protected function getUnpackBlockSize(): int
    {
        return 22;
    }
}

class phpMorphy_AnnotDecoder_Predict extends phpMorphy_AnnotDecoder_Common
{
    protected function getUnpackString(): string
    {
        return parent::getUnpackString().'/vfreq';
    }

    protected function getUnpackBlockSize(): int
    {
        return parent::getUnpackBlockSize() + 2;
    }
}

class phpMorphy_AnnotDecoder_Factory
{
    protected static array $instances = [];

    protected $cache_common;

    protected $cache_predict;

    protected $eos;

    protected function __construct($eos)
    {
        $this->eos = $eos;
    }

    public static function create($eos)
    {
        if (! isset(self::$instances[$eos])) {
            self::$instances[$eos] = new phpMorphy_AnnotDecoder_Factory($eos);
        }

        return self::$instances[$eos];
    }

    public function getCommonDecoder()
    {
        if (! isset($this->cache_common)) {
            $this->cache_common = $this->instantinate('common');
        }

        return $this->cache_common;
    }

    public function getPredictDecoder()
    {
        if (! isset($this->cache_predict)) {
            $this->cache_predict = $this->instantinate('predict');
        }

        return $this->cache_predict;
    }

    protected function instantinate($type)
    {
        $clazz = 'phpMorphy_AnnotDecoder_'.ucfirst($GLOBALS['__phpmorphy_strtolower']($type));

        return new $clazz($this->eos);
    }
}

class phpMorphy_AncodesResolver_Proxy implements AncodesResolverInterface
{
    protected $args;

    protected $class;

    protected ?object $__obj;

    public function __construct($class, $ctorArgs)
    {
        $this->class = $class;
        $this->args = $ctorArgs;
    }

    /**
     * @throws ReflectionException
     */
    public function unresolve($ancode)
    {
        return $this->getObj()->unresolve($ancode);
    }

    /**
     * @throws ReflectionException
     */
    public function resolve($ancodeId)
    {
        return $this->getObj()->resolve($ancodeId);
    }

    /**
     * @throws ReflectionException
     */
    public static function instantinate($class, $args)
    {
        $ref = new ReflectionClass($class);

        return $ref->newInstanceArgs($args);
    }

    /**
     * @throws ReflectionException
     */
    public function getObj()
    {
        if ($this->__obj !== null) {
            return $this->__obj;
        }
        $this->__obj = $this->instantinate($this->class, $this->args);

        unset($this->args);
        unset($this->class);

        return $this->__obj;
    }
}

class phpMorphy_AncodesResolver_ToText implements AncodesResolverInterface
{
    protected GramTabInterface $gramtab;

    public function __construct(GramTabInterface $gramtab)
    {
        $this->gramtab = $gramtab;
    }

    public function resolve($ancodeId)
    {
        if (! isset($ancodeId)) {
            return null;
        }

        return $this->gramtab->ancodeToString($ancodeId);
    }

    public function unresolve($ancode)
    {
        return $this->gramtab->stringToAncode($ancode);
        // throw new phpMorphy_Exception("Can`t convert grammar info in text into ancode id");
    }
}

class phpMorphy_AncodesResolver_ToDialingAncodes implements AncodesResolverInterface
{
    protected array $reverse_map;

    protected mixed $ancodes_map;

    /**
     * @throws phpMorphy_Exception
     */
    public function __construct(phpMorphy_Storage $ancodesMap)
    {
        if (false === ($this->ancodes_map = unserialize($ancodesMap->read(0, $ancodesMap->getFileSize())))) {
            throw new phpMorphy_Exception('Can`t open phpMorphy => Dialing ancodes map');
        }

        $this->reverse_map = array_flip($this->ancodes_map);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function unresolve($ancode): int|string|null
    {
        if (! isset($ancode)) {
            return null;
        }

        if (! isset($this->reverse_map[$ancode])) {
            throw new phpMorphy_Exception("Unknwon ancode found '$ancode'");
        }

        return $this->reverse_map[$ancode];
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function resolve($ancodeId)
    {
        if (! isset($ancodeId)) {
            return null;
        }

        if (! isset($this->ancodes_map[$ancodeId])) {
            throw new phpMorphy_Exception("Unknwon ancode id found '$ancodeId'");
        }

        return $this->ancodes_map[$ancodeId];
    }
}

class phpMorphy_AncodesResolver_AsIs implements AncodesResolverInterface
{
    // This ctor for ReflectionClass::newInstanceArgs($args) with $args = array()
    public function __construct() {}

    public function resolve($ancodeId)
    {
        return $ancodeId;
    }

    public function unresolve($ancode)
    {
        return $ancode;
    }
}

// ----------------------------
// Helper
// ----------------------------
class phpMorphy_Morphier_Helper
{
    protected AnnotDecoderInterface $annot_decoder;

    protected int $char_size;

    protected string $ends;

    protected AncodesResolverInterface $ancodes_resolver;

    protected phpMorphy_GramInfo_Interace $graminfo;

    protected bool $gramtab_consts_included = false;

    protected bool $resolve_pos;

    protected GramTabInterface $gramtab;

    public function __construct(phpMorphy_GramInfo_Interace $graminfo, GramTabInterface $gramtab, AncodesResolverInterface $ancodesResolver, $resolvePartOfSpeech)
    {
        $this->graminfo = $graminfo;
        $this->gramtab = $gramtab;
        $this->resolve_pos = (bool) $resolvePartOfSpeech;
        $this->ancodes_resolver = $ancodesResolver;

        $this->char_size = $graminfo->getCharSize();

        $this->ends = $graminfo->getEnds();
    }

    public function setAnnotDecoder(AnnotDecoderInterface $annotDecoder): void
    {
        $this->annot_decoder = $annotDecoder;
    }

    // getters
    public function getEndOfString(): string
    {
        return $this->ends;
    }

    public function getCharSize(): int
    {
        return $this->char_size;
    }

    public function hasAnnotDecoder(): bool
    {
        return isset($this->annot_decoder);
    }

    public function getAnnotDecoder(): AnnotDecoderInterface
    {
        return $this->annot_decoder;
    }

    public function getAncodesResolver(): AncodesResolverInterface
    {
        return $this->ancodes_resolver;
    }

    public function getGramInfo(): phpMorphy_GramInfo_Interace
    {
        return $this->graminfo;
    }

    public function getGramTab(): GramTabInterface
    {
        return $this->gramtab;
    }

    public function isResolvePartOfSpeech(): bool
    {
        return $this->resolve_pos;
    }

    // other
    public function resolvePartOfSpeech($posId)
    {
        return $this->gramtab->resolvePartOfSpeechId($posId);
    }

    public function getGrammems($ancodeId)
    {
        return $this->gramtab->getGrammems($ancodeId);
    }

    public function getGrammemsAndPartOfSpeech(int $ancodeId): array
    {
        return [
            $this->gramtab->getPartOfSpeech($ancodeId),
            $this->gramtab->getGrammems($ancodeId),
        ];
    }

    public function extractPartOfSpeech($annot)
    {
        if ($this->resolve_pos) {
            return $this->resolvePartOfSpeech($annot['pos_id']);
        } else {
            return $annot['pos_id'];
        }
    }

    protected function includeGramTabConsts(): void
    {
        if ($this->isResolvePartOfSpeech()) {
            $this->gramtab->includeConsts();
        }

        $this->gramtab_consts_included = true;
    }

    // getters
    public function getWordDescriptor($word, bool|array $annots): phpMorphy_WordDescriptor_Collection
    {
        if (! $this->gramtab_consts_included) {
            $this->includeGramTabConsts();
        }

        return new phpMorphy_WordDescriptor_Collection($word, $annots, $this);
    }

    protected function getBaseAndPrefix(string $word, int $cpLen, int $pLen, int $fLen): array
    {
        if ($fLen !== 0) {
            $base = $GLOBALS['__phpmorphy_substr']($word, $cpLen + $pLen, -$fLen);
        } else {
            if ($cpLen || $pLen) {
                $base = $GLOBALS['__phpmorphy_substr']($word, $cpLen + $pLen);
            } else {
                $base = $word;
            }
        }

        $prefix = $cpLen ? $GLOBALS['__phpmorphy_substr']($word, 0, $cpLen) : '';

        return [$base, $prefix];
    }

    public function getPartOfSpeech(string $word, array|bool $annots): array|bool
    {
        if ($annots === false) {
            return false;
        }

        $result = [];

        foreach ($this->decodeAnnot($annots, false) as $annot) {
            $result[$this->extractPartOfSpeech($annot)] = 1;
        }

        return array_keys($result);
    }

    public function getBaseForm($word, $annots): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $annots = $this->decodeAnnot($annots, true);

        return $this->composeBaseForms($word, $annots);
    }

    public function getPseudoRoot($word, $annots): array|bool
    {
        if ($annots === false) {
            return false;
        }

        $annots = $this->decodeAnnot($annots, false);

        $result = [];

        foreach ($annots as $annot) {
            [$base] = $this->getBaseAndPrefix(
                $word,
                $annot['cplen'],
                $annot['plen'],
                $annot['flen']
            );

            $result[$base] = 1;
        }

        return array_keys($result);
    }

    public function getAllForms($word, $annots): array|bool
    {
        if ($annots === false) {
            return false;
        }

        $annots = $this->decodeAnnot($annots, false);

        return $this->composeForms($word, $annots);
    }

    /**
     * Склоняет слово по заданным грамматическим характеристикам.
     *
     * @param  string  $word  Слово для склонения.
     * @param  bool|array  $annots  Аннотации для слова.
     * @param  PartOfSpeech  $partOfSpeech  Часть речи (опционально).
     * @param  array<Grammems>  $grammems  Массив грамматических характеристик.
     * @param  bool  $returnWords  Если true, возвращает только слова, без дополнительной информации.
     * @param  callable|null  $callback  Callback-функция для обработки результата.
     * @return array|bool Результат склонения или false в случае ошибки.
     */
    public function castFormByGramInfo(string $word, bool|array $annots, PartOfSpeech $partOfSpeech, array $grammems, bool $returnWords = false, ?callable $callback = null): array|bool
    {
        if ($annots === false) {
            return false;
        }

        $grammemsValues = array_map(fn (Grammems $grammem) => $grammem->value, $grammems);
        $result = [];

        foreach ($this->decodeAnnot($annots, false) as $annot) {
            $allAncodes = $this->graminfo->readAncodes($annot);
            $flexias = $this->graminfo->readFlexiaData($annot);
            $commonAncode = $annot['common_ancode'];
            $commonGrammems = isset($commonAncode) ? $this->gramtab->getGrammems($commonAncode) : [];

            [$base, $prefix] = $this->getBaseAndPrefix(
                $word,
                $annot['cplen'],
                $annot['plen'],
                $annot['flen']
            );

            $i = 0;
            $formNo = 0;
            foreach ($allAncodes as $formAncodes) {
                foreach ($formAncodes as $ancode) {
                    $formPos = $this->gramtab->getPartOfSpeech($ancode);
                    $formGrammems = array_merge($this->gramtab->getGrammems($ancode), $commonGrammems);
                    $form = $prefix.$flexias[$i].$base.$flexias[$i + 1];

                    // Проверяем, соответствует ли форма критериям
                    if ($callback !== null) {
                        if (! call_user_func($callback, $form, $formPos, $formGrammems, $formNo)) {
                            $formNo++;

                            continue;
                        }
                    } else {
                        if ($formPos !== $partOfSpeech->value) {
                            $formNo++;

                            continue;
                        }

                        if (count(array_diff($grammemsValues, $formGrammems)) > 0) {
                            $formNo++;

                            continue;
                        }
                    }

                    // Добавляем результат
                    if ($returnWords) {
                        $result[$form] = 1;
                    } else {
                        $result[] = [
                            'form' => $form,
                            'form_no' => $formNo,
                            'pos' => $formPos,
                            'grammems' => $formGrammems,
                        ];
                    }

                    $formNo++;
                }
                $i += 2;
            }
        }

        return $returnWords ? array_keys($result) : $result;
    }

    public function getAncode($annots): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $result = [];

        foreach ($this->decodeAnnot($annots, false) as $annot) {
            $all_ancodes = $this->graminfo->readAncodes($annot);

            $result[] = [
                'common' => $this->ancodes_resolver->resolve($annot['common_ancode']),
                'all' => array_map(
                    [$this->ancodes_resolver, 'resolve'],
                    $all_ancodes[$annot['form_no']]
                ),
            ];
        }

        return $this->array_unique($result);
    }

    protected static function array_unique($array): array
    {
        static $need_own;

        if (! isset($need_own)) {
            $need_own = version_compare(PHP_VERSION, '5.2.9') === -1;
        }

        if ($need_own) {
            $result = [];

            foreach (array_keys(array_unique(array_map('serialize', $array))) as $key) {
                $result[$key] = $array[$key];
            }

            return $result;
        } else {
            return array_unique($array, SORT_REGULAR);
        }
    }

    public function getGrammarInfoMergeForms($annots): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $result = [];

        foreach ($this->decodeAnnot($annots, false) as $annot) {
            $all_ancodes = $this->graminfo->readAncodes($annot);
            $common_ancode = $annot['common_ancode'];
            $grammems = isset($common_ancode) ? $this->gramtab->getGrammems($common_ancode) : [];

            $forms_count = 0;
            $form_no = $annot['form_no'];

            foreach ($all_ancodes[$form_no] as $ancode) {
                $grammems = array_merge($grammems, $this->gramtab->getGrammems($ancode));
                $forms_count++;
            }

            $grammems = array_unique($grammems);
            sort($grammems);

            $result[] = [
                // part of speech identical across all joined forms
                'pos' => $this->gramtab->getPartOfSpeech($ancode),
                'grammems' => $grammems,
                'forms_count' => $forms_count,
                'form_no_low' => $form_no,
                'form_no_high' => $form_no + $forms_count,
            ];
        }

        return $this->array_unique($result);
    }

    public function getGrammarInfo($annots): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $result = [];

        foreach ($this->decodeAnnot($annots, false) as $annot) {
            $all_ancodes = $this->graminfo->readAncodes($annot);
            $common_ancode = $annot['common_ancode'];
            $common_grammems = isset($common_ancode) ? $this->gramtab->getGrammems($common_ancode) : [];

            $info = [];

            $form_no = $annot['form_no'];
            foreach ($all_ancodes[$form_no] as $ancode) {
                $grammems = array_merge($common_grammems, $this->gramtab->getGrammems($ancode));

                sort($grammems);

                $info_item = [
                    'pos' => $this->gramtab->getPartOfSpeech($ancode),
                    'grammems' => $grammems,
                    'form_no' => $form_no,
                ];

                $info[] = $info_item;
            }

            $unique_info = $this->array_unique($info);
            sort($unique_info);
            $result[] = $unique_info;
        }

        return $this->array_unique($result);
    }

    public function getAllFormsWithResolvedAncodes($word, $annots, $resolveType = 'no_resolve'): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $annots = $this->decodeAnnot($annots, false);

        return $this->composeFormsWithResolvedAncodes($word, $annots);
    }

    public function getAllFormsWithAncodes($word, $annots, &$foundFormNo = []): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $annots = $this->decodeAnnot($annots, false);

        return $this->composeFormsWithAncodes($word, $annots, $foundFormNo);
    }

    public function getAllAncodes($word, $annots): bool|array
    {
        if ($annots === false) {
            return false;
        }

        $result = [];

        foreach ($annots as $annot) {
            $result[] = $this->graminfo->readAncodes($annot);
        }

        return $result;
    }

    protected function composeBaseForms($word, $annots): array
    {
        $result = [];

        foreach ($annots as $annot) {

            if ($annot['form_no'] > 0) {
                [$base, $prefix] = $this->getBaseAndPrefix(
                    $word,
                    $annot['cplen'],
                    $annot['plen'],
                    $annot['flen']
                );

                $result[$prefix.$annot['base_prefix'].$base.$annot['base_suffix']] = 1;
            } else {
                $result[$word] = 1;
            }
        }

        return array_keys($result);
    }

    protected function composeForms($word, $annots): array
    {
        $result = [];

        foreach ($annots as $annot) {
            [$base, $prefix] = $this->getBaseAndPrefix(
                $word,
                $annot['cplen'],
                $annot['plen'],
                $annot['flen']
            );

            // read flexia
            $flexias = $this->graminfo->readFlexiaData($annot);

            for ($i = 0, $c = count($flexias); $i < $c; $i += 2) {
                $result[$prefix.$flexias[$i].$base.$flexias[$i + 1]] = 1;
            }
        }

        return array_keys($result);
    }

    protected function composeFormsWithResolvedAncodes($word, $annots): array
    {
        $result = [];

        foreach ($annots as $annotIdx => $annot) {
            [$base, $prefix] = $this->getBaseAndPrefix(
                $word,
                $annot['cplen'],
                $annot['plen'],
                $annot['flen']
            );

            $words = [];
            $ancodes = [];
            $common_ancode = $annot['common_ancode'];

            // read flexia
            $flexias = $this->graminfo->readFlexiaData($annot);
            $all_ancodes = $this->graminfo->readAncodes($annot);

            for ($i = 0, $c = count($flexias); $i < $c; $i += 2) {
                $form = $prefix.$flexias[$i].$base.$flexias[$i + 1];

                $current_ancodes = $all_ancodes[$i / 2];
                foreach ($current_ancodes as $ancode) {
                    $words[] = $form;
                    $ancodes[] = $this->ancodes_resolver->resolve($ancode);
                }
            }

            $result[] = [
                'forms' => $words,
                'common' => $this->ancodes_resolver->resolve($common_ancode),
                'all' => $ancodes,
            ];
        }

        return $result;
    }

    protected function composeFormsWithAncodes($word, $annots, &$foundFormNo): array
    {
        $result = [];

        foreach ($annots as $annotIdx => $annot) {
            [$base, $prefix] = $this->getBaseAndPrefix(
                $word,
                $annot['cplen'],
                $annot['plen'],
                $annot['flen']
            );

            // read flexia
            $flexias = $this->graminfo->readFlexiaData($annot);
            $ancodes = $this->graminfo->readAncodes($annot);

            $found_form_no = $annot['form_no'];

            $foundFormNo = ! is_array($foundFormNo) ? [] : $foundFormNo;

            for ($i = 0, $c = count($flexias); $i < $c; $i += 2) {
                $form_no = $i / 2;
                $word = $prefix.$flexias[$i].$base.$flexias[$i + 1];

                if ($found_form_no == $form_no) {
                    $count = count($result);
                    $foundFormNo[$annotIdx]['low'] = $count;
                    $foundFormNo[$annotIdx]['high'] = $count + count($ancodes[$form_no]) - 1;
                }

                foreach ($ancodes[$form_no] as $ancode) {
                    $result[] = [$word, $ancode];
                }
            }
        }

        return $result;
    }

    public function decodeAnnot($annotsRaw, $withBase)
    {
        if (is_array($annotsRaw)) {
            return $annotsRaw;
        } else {
            return $this->annot_decoder->decode($annotsRaw, $withBase);
        }
    }
}

// ----------------------------
// WordDescriptor
// ----------------------------
// TODO: extend ArrayObject?
class phpMorphy_WordDescriptor_Collection implements ArrayAccess, Countable, IteratorAggregate
{
    protected array $descriptors = [];

    protected phpMorphy_Morphier_Helper $helper;

    protected string $word;

    protected array|false $annots;

    public function __construct(string $word, $annots, phpMorphy_Morphier_Helper $helper)
    {
        $this->word = $word;
        $this->annots = $annots === false ? false : $helper->decodeAnnot($annots, true);

        $this->helper = $helper;

        if ($this->annots !== false) {
            foreach ($this->annots as $annot) {
                $this->descriptors[] = $this->createDescriptor($word, $annot, $helper);
            }
        }
    }

    protected function createDescriptor($word, $annot, phpMorphy_Morphier_Helper $helper): phpMorphy_WordDescriptor
    {
        return new phpMorphy_WordDescriptor($word, $annot, $helper);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getDescriptor($index)
    {
        if (! $this->offsetExists($index)) {
            throw new phpMorphy_Exception("Invalid index '$index' specified");
        }

        return $this->descriptors[$index];
    }

    public function getByPartOfSpeech($poses): array
    {
        $result = [];
        settype($poses, 'array');

        foreach ($this as $desc) {
            if ($desc->hasPartOfSpeech($poses)) {
                $result[] = $desc;
            }
        }

        return $result;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->descriptors[$offset]);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetUnset($offset): void
    {
        throw new phpMorphy_Exception(__CLASS__.' is not mutable');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetSet($offset, $value): void
    {
        throw new phpMorphy_Exception(__CLASS__.' is not mutable');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetGet($offset): mixed
    {
        return $this->getDescriptor($offset);
    }

    public function count(): int
    {
        return count($this->descriptors);
    }

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->descriptors);
    }
}

class phpMorphy_WordForm
{
    protected $pos_id;

    protected $grammems;

    protected int $form_no;

    protected string $word;

    public function __construct($word, $form_no, $pos_id, $grammems)
    {
        $this->word = (string) $word;
        $this->form_no = (int) $form_no;
        $this->pos_id = $pos_id;

        sort($grammems);
        $this->grammems = $grammems;
    }

    public function getPartOfSpeech()
    {
        return $this->pos_id;
    }

    public function getGrammems()
    {
        return $this->grammems;
    }

    public function hasGrammems($grammems): bool
    {
        $grammems = (array) $grammems;

        $grammes_count = count($grammems);

        return $grammes_count && count(array_intersect($grammems, $this->grammems)) == $grammes_count;
    }

    public static function compareGrammems($a, $b): bool
    {
        return count($a) == count($b) && count(array_diff($a, $b)) == 0;
    }

    public function getWord(): string
    {
        return $this->word;
    }

    public function getFormNo(): int
    {
        return $this->form_no;
    }
}

class phpMorphy_WordDescriptor implements ArrayAccess, Countable, IteratorAggregate
{
    protected $cached_forms;

    protected $cached_base;

    protected $cached_pseudo_root;

    protected $found_form_no;

    protected $common_ancode_grammems;

    protected array|bool $all_forms;

    protected phpMorphy_Morphier_Helper $helper;

    protected array $annot;

    protected string $word;

    public function __construct($word, $annot, phpMorphy_Morphier_Helper $helper)
    {
        $this->word = (string) $word;
        $this->annot = [$annot];

        $this->helper = $helper;
    }

    public function getPseudoRoot()
    {
        if (! isset($this->cached_pseudo_root)) {
            [$this->cached_pseudo_root] = $this->helper->getPseudoRoot($this->word, $this->annot);
        }

        return $this->cached_pseudo_root;
    }

    public function getBaseForm()
    {
        if (! isset($this->cached_base)) {
            [$this->cached_base] = $this->helper->getBaseForm($this->word, $this->annot);
        }

        return $this->cached_base;
    }

    public function getAllForms(): bool|array
    {
        if (! isset($this->cached_forms)) {
            $this->cached_forms = $this->helper->getAllForms($this->word, $this->annot);
        }

        return $this->cached_forms;
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getWordForm($index)
    {
        $this->readAllForms();

        if (! $this->offsetExists($index)) {
            throw new phpMorphy_Exception("Invalid index '$index' given");
        }

        return $this->all_forms[$index];
    }

    protected function createWordForm($word, $form_no, $ancode): phpMorphy_WordForm
    {
        if (! isset($this->common_ancode_grammems)) {
            $common_ancode = $this->annot[0]['common_ancode'];

            $this->common_ancode_grammems = isset($common_ancode) ?
                $this->helper->getGrammems($common_ancode) :
                [];
        }

        [$pos_id, $all_grammems] = $this->helper->getGrammemsAndPartOfSpeech($ancode);

        return new phpMorphy_WordForm($word, $form_no, $pos_id, array_merge($this->common_ancode_grammems, $all_grammems));
    }

    protected function readAllForms(): array
    {
        if (! isset($this->all_forms)) {
            $result = [];

            $form_no = 0;

            $found_form_no = [];
            foreach ($this->helper->getAllFormsWithAncodes($this->word, $this->annot, $found_form_no) as $form) {
                $word = $form[0];

                $result[] = $this->createWordForm($word, $form_no, $form[1]);

                $form_no++;
            }

            $this->found_form_no = $found_form_no[0];
            $this->all_forms = $result;
        }

        return $this->all_forms;
    }

    protected function getFoundFormNoLow()
    {
        $this->readAllForms();

        return $this->found_form_no['low'];
    }

    protected function getFoundFormNoHigh()
    {
        $this->readAllForms();

        return $this->found_form_no['high'];
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getFoundWordForm(): array
    {
        $result = [];
        for ($i = $this->getFoundFormNoLow(), $c = $this->getFoundFormNoHigh() + 1; $i < $c; $i++) {
            $result[] = $this->getWordForm($i);
        }

        return $result;
    }

    public function hasGrammems($grammems): bool
    {
        settype($grammems, 'array');

        foreach ($this as $wf) {
            if ($wf->hasGrammems($grammems)) {
                return true;
            }
        }

        return false;
    }

    public function getWordFormsByGrammems($grammems): array
    {
        settype($grammems, 'array');
        $result = [];

        foreach ($this as $wf) {
            if ($wf->hasGrammems($grammems)) {
                $result[] = $wf;
            }
        }

        return $result;
    }

    public function hasPartOfSpeech($poses): bool
    {
        settype($poses, 'array');

        foreach ($this as $wf) {
            if (in_array($wf->getPartOfSpeech(), $poses, true)) {
                return true;
            }
        }

        return false;
    }

    public function getWordFormsByPartOfSpeech($poses): array
    {
        settype($poses, 'array');
        $result = [];

        foreach ($this as $wf) {
            if (in_array($wf->getPartOfSpeech(), $poses, true)) {
                $result[] = $wf;
            }
        }

        return $result;
    }

    public function count(): int
    {
        return count($this->readAllForms());
    }

    public function offsetExists($offset): bool
    {
        $this->readAllForms();

        return isset($this->all_forms[$offset]);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetSet($offset, $value): void
    {
        throw new phpMorphy_Exception(__CLASS__.' is not mutable');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetUnset($offset): void
    {
        throw new phpMorphy_Exception(__CLASS__.' is not mutable');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function offsetGet($offset): mixed
    {
        return $this->getWordForm($offset);
    }

    public function getIterator(): \Traversable
    {
        $this->readAllForms();

        return new ArrayIterator($this->all_forms);
    }
}

// ----------------------------
// Finders
// ----------------------------

abstract class phpMorphy_Morphier_Finder_Base implements MorphierFinderInterface
{
    protected $prev_word;

    protected bool $prev_result = false;

    protected AnnotDecoderInterface $annot_decoder;

    public function __construct(AnnotDecoderInterface $annotDecoder)
    {
        $this->annot_decoder = $annotDecoder;
    }

    public function findWord($word): bool
    {
        if ($this->prev_word === $word) {
            return $this->prev_result;
        }

        $result = $this->doFindWord($word);

        $this->prev_word = $word;
        $this->prev_result = $result;

        return $result;
    }

    public function getAnnotDecoder(): AnnotDecoderInterface
    {
        return $this->annot_decoder;
    }

    public function decodeAnnot($raw, $withBase)
    {
        return $this->annot_decoder->decode($raw, $withBase);
    }

    abstract protected function doFindWord($word);
}

class phpMorphy_Morphier_Finder_Common extends phpMorphy_Morphier_Finder_Base
{
    protected array|int $root;

    protected FsaInterface $fsa;

    public function __construct(FsaInterface $fsa, AnnotDecoderInterface $annotDecoder)
    {
        parent::__construct($annotDecoder);

        $this->fsa = $fsa;
        $this->root = $this->fsa->getRootTrans();
    }

    public function getFsa(): FsaInterface
    {
        return $this->fsa;
    }

    protected function doFindWord($word)
    {
        $result = $this->fsa->walk($this->root, $word);

        if (! $result['result'] || $result['annot'] === null) {
            return false;
        }

        return $result['annot'];
    }
}

class phpMorphy_Morphier_Finder_Predict_Suffix extends phpMorphy_Morphier_Finder_Common
{
    protected mixed $unicode;

    protected int $min_suf_len;

    public function __construct(FsaInterface $fsa, AnnotDecoderInterface $annotDecoder, $encoding, $minimalSuffixLength = 4)
    {
        parent::__construct($fsa, $annotDecoder);

        $this->min_suf_len = (int) $minimalSuffixLength;
        $this->unicode = phpMorphy_UnicodeHelper::create($encoding);
    }

    protected function doFindWord($word)
    {
        $word_len = $this->unicode->strlen($word);

        if (! $word_len) {
            return false;
        }

        for ($i = 1, $c = $word_len - $this->min_suf_len; $i < $c; $i++) {
            $word = $GLOBALS['__phpmorphy_substr']($word, $this->unicode->firstCharSize($word));

            if (false !== ($result = parent::doFindWord($word))) {
                break;
            }
        }

        if ($i < $c) {
            // $known_len = $word_len - $i;
            $unknown_len = $i;

            return $result;
            /*
            return $this->fixAnnots(
                $this->decodeAnnot($result, true),
                $unknown_len
            );
            */
        } else {
            return false;
        }
    }

    protected function fixAnnots($annots, $len)
    {
        for ($i = 0, $c = count($annots); $i < $c; $i++) {
            $annots[$i]['cplen'] = $len;
        }

        return $annots;
    }
}

class phpMorphy_Morphier_PredictCollector extends phpMorphy_Fsa_WordsCollector
{
    protected AnnotDecoderInterface $annot_decoder;

    protected int $collected = 0;

    protected array $used_poses = [];

    public function __construct($limit, AnnotDecoderInterface $annotDecoder)
    {
        parent::__construct($limit);

        $this->annot_decoder = $annotDecoder;
    }

    public function collect($word, $annot): bool
    {
        if ($this->collected > $this->limit) {
            return false;
        }

        $used_poses = &$this->used_poses;
        $annots = $this->decodeAnnot($annot);

        for ($i = 0, $c = count($annots); $i < $c; $i++) {
            $annot = $annots[$i];
            $annot['cplen'] = $annot['plen'] = 0;

            $pos_id = $annot['pos_id'];

            if (isset($used_poses[$pos_id])) {
                $result_idx = $used_poses[$pos_id];

                if ($annot['freq'] > $this->items[$result_idx]['freq']) {
                    $this->items[$result_idx] = $annot;
                }
            } else {
                $used_poses[$pos_id] = count($this->items);
                $this->items[] = $annot;
            }
        }

        $this->collected++;

        return true;
    }

    public function clear(): void
    {
        parent::clear();
        $this->collected = 0;
        $this->used_poses = [];
    }

    public function decodeAnnot($annotRaw)
    {
        return $this->annot_decoder->decode($annotRaw, true);
    }
}

class phpMorphy_Morphier_Finder_Predict_Databse extends phpMorphy_Morphier_Finder_Common
{
    protected $unicode;

    protected $min_postfix_match;

    protected phpMorphy_GramInfo_Interace $graminfo;

    protected phpMorphy_Morphier_PredictCollector $collector;

    public function __construct(
        FsaInterface $fsa,
        AnnotDecoderInterface $annotDecoder,
        $encoding,
        phpMorphy_GramInfo_Interace $graminfo,
        $minPostfixMatch = 2,
        $collectLimit = 32
    ) {
        parent::__construct($fsa, $annotDecoder);

        $this->graminfo = $graminfo;
        $this->min_postfix_match = $minPostfixMatch;
        $this->collector = $this->createCollector($collectLimit, $this->getAnnotDecoder());

        $this->unicode = phpMorphy_UnicodeHelper::create($encoding);
    }

    protected function doFindWord($word): false|array
    {
        $rev_word = $this->unicode->strrev($word);
        $result = $this->fsa->walk($this->root, $rev_word);

        if ($result['result'] && $result['annot'] !== null) {
            $annots = $result['annot'];
        } else {
            $match_len = $this->unicode->strlen($this->unicode->fixTrailing($GLOBALS['__phpmorphy_substr']($rev_word, 0, $result['walked'])));

            if (null === ($annots = $this->determineAnnots($result['last_trans'], $match_len))) {
                return false;
            }
        }

        if (! is_array($annots)) {
            $annots = $this->collector->decodeAnnot($annots);
        }

        return $this->fixAnnots($word, $annots);
    }

    protected function determineAnnots($trans, $matchLen): array|string
    {
        $annots = $this->fsa->getAnnot($trans);

        if ($annots == null && $matchLen >= $this->min_postfix_match) {
            $this->collector->clear();

            $this->fsa->collect(
                $trans,
                $this->collector->getCallback()
            );

            $annots = $this->collector->getItems();
        }

        return $annots;
    }

    protected function fixAnnots($word, $annots): false|array
    {
        $result = [];

        // remove all prefixes?
        for ($i = 0, $c = count($annots); $i < $c; $i++) {
            $annot = $annots[$i];

            $annot['cplen'] = $annot['plen'] = 0;

            $flexias = $this->graminfo->readFlexiaData($annot);

            $prefix = $flexias[$annot['form_no'] * 2];
            $suffix = $flexias[$annot['form_no'] * 2 + 1];

            $plen = $GLOBALS['__phpmorphy_strlen']($prefix);
            $slen = $GLOBALS['__phpmorphy_strlen']($suffix);
            if (
                (! $plen || $prefix === $GLOBALS['__phpmorphy_substr']($word, 0, $GLOBALS['__phpmorphy_strlen']($prefix))) &&
                (! $slen || $suffix === $GLOBALS['__phpmorphy_substr']($word, -$GLOBALS['__phpmorphy_strlen']($suffix)))
            ) {
                $result[] = $annot;
            }
        }

        return count($result) ? $result : false;
    }

    protected function createCollector($limit): phpMorphy_Morphier_PredictCollector
    {
        return new phpMorphy_Morphier_PredictCollector($limit, $this->getAnnotDecoder());
    }
}

// ----------------------------
// Morphiers
// ----------------------------
abstract class phpMorphy_Morphier_Base implements MorphierInterface
{
    protected MorphierFinderInterface $finder;

    protected phpMorphy_Morphier_Helper $helper;

    public function __construct(MorphierFinderInterface $finder, phpMorphy_Morphier_Helper $helper)
    {
        $this->finder = $finder;

        $this->helper = clone $helper;
        $this->helper->setAnnotDecoder($finder->getAnnotDecoder());
    }

    public function getFinder(): MorphierFinderInterface
    {
        return $this->finder;
    }

    public function getHelper(): phpMorphy_Morphier_Helper
    {
        return $this->helper;
    }

    public function getAnnot($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->decodeAnnot($annots, true);
    }

    public function getWordDescriptor($word): bool|phpMorphy_WordDescriptor_Collection
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getWordDescriptor($word, $annots);
    }

    public function getAllFormsWithAncodes($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getAllFormsWithResolvedAncodes($word, $annots);
    }

    public function getPartOfSpeech(string|array $word): array|bool
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getPartOfSpeech($word, $annots);
    }

    public function getBaseForm($word): array|bool
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getBaseForm($word, $annots);
    }

    public function getPseudoRoot(string|array $word): array|bool
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getPseudoRoot($word, $annots);
    }

    public function getAllForms($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getAllForms($word, $annots);
    }

    public function getAncode($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getAncode($annots);
    }

    public function getGrammarInfo($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getGrammarInfo($annots);
    }

    public function getGrammarInfoMergeForms($word): bool|array
    {
        if (false === ($annots = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->getGrammarInfoMergeForms($annots);
    }

    /**
     * Склоняет слово по заданным грамматическим характеристикам.
     *
     * @param  string  $word  Слово для склонения.
     * @param  PartOfSpeech  $partOfSpeech  Часть речи.
     * @param  array<Grammems>  $grammems  Массив грамматических характеристик.
     * @param  bool  $returnOnlyWord  Если true, возвращает только слово, без дополнительной информации.
     * @param  callable|null  $callback  Callback-функция для обработки результата.
     * @return array|bool Результат склонения или false в случае ошибки.
     *
     * @throws InvalidArgumentException Если массив $grammems содержит недопустимые элементы.
     */
    public function castFormByGramInfo(string $word, PartOfSpeech $partOfSpeech, array $grammems, bool $returnOnlyWord = false, ?callable $callback = null): array|bool
    {
        if (array_filter($grammems, fn ($grammem) => ! $grammem instanceof Grammems)) {
            throw new InvalidArgumentException('All elements in $grammems must be instances of Grammems.');
        }

        if (false === ($annot = $this->finder->findWord($word))) {
            return false;
        }

        return $this->helper->castFormByGramInfo($word, $annot, $partOfSpeech, $grammems, $returnOnlyWord, $callback);
    }

    public function castFormByPattern($word, $patternWord, $returnOnlyWord = false, $callback = null)
    {
        if (false === ($orig_annots = $this->finder->findWord($word))) {
            return false;
        }

        if (false === ($pattern_annots = $this->finder->findWord($patternWord))) {
            return false;
        }

        return $this->helper->castFormByPattern(
            $word, $orig_annots,
            $patternWord, $pattern_annots,
            $returnOnlyWord,
            $callback
        );
    }
}

class phpMorphy_Morphier_Common extends phpMorphy_Morphier_Base
{
    public function __construct(FsaInterface $fsa, phpMorphy_Morphier_Helper $helper)
    {
        parent::__construct(
            new phpMorphy_Morphier_Finder_Common(
                $fsa,
                $this->createAnnotDecoder($helper)
            ),
            $helper
        );
    }

    protected function createAnnotDecoder(phpMorphy_Morphier_Helper $helper)
    {
        return phpMorphy_AnnotDecoder_Factory::create($helper->getGramInfo()->getEnds())->getCommonDecoder();
    }
}

class phpMorphy_Morphier_Predict_Suffix extends phpMorphy_Morphier_Base
{
    public function __construct(FsaInterface $fsa, phpMorphy_Morphier_Helper $helper)
    {
        parent::__construct(
            new phpMorphy_Morphier_Finder_Predict_Suffix(
                $fsa,
                $this->createAnnotDecoder($helper),
                $helper->getGramInfo()->getEncoding(),
                4
            ),
            $helper
        );
    }

    protected function createAnnotDecoder(phpMorphy_Morphier_Helper $helper)
    {
        return phpMorphy_AnnotDecoder_Factory::create($helper->getGramInfo()->getEnds())->getCommonDecoder();
    }
}

class phpMorphy_Morphier_Predict_Database extends phpMorphy_Morphier_Base
{
    public function __construct(FsaInterface $fsa, phpMorphy_Morphier_Helper $helper)
    {
        parent::__construct(
            new phpMorphy_Morphier_Finder_Predict_Databse(
                $fsa,
                $this->createAnnotDecoder($helper),
                $helper->getGramInfo()->getEncoding(),
                $helper->getGramInfo(),
                2,
                32
            ),
            $helper
        );
    }

    protected function createAnnotDecoder(phpMorphy_Morphier_Helper $helper)
    {
        return phpMorphy_AnnotDecoder_Factory::create($helper->getGramInfo()->getEnds())->getPredictDecoder();
    }
}

class phpMorphy_Morphier_Bulk implements MorphierInterface
{
    protected array $notfound = [];

    protected phpMorphy_GramInfo_Interace $graminfo;

    protected FsaInterface $fsa;

    protected array $root_trans;

    protected phpMorphy_Morphier_Helper $helper;

    public function __construct(FsaInterface $fsa, phpMorphy_Morphier_Helper $helper)
    {
        $this->fsa = $fsa;
        $this->root_trans = $fsa->getRootTrans();

        $this->helper = clone $helper;
        $this->helper->setAnnotDecoder($this->createAnnotDecoder($helper));

        $this->graminfo = $helper->getGramInfo();
    }

    public function getFsa(): FsaInterface
    {
        return $this->fsa;
    }

    public function getHelper(): phpMorphy_Morphier_Helper
    {
        return $this->helper;
    }

    public function getGramInfo(): phpMorphy_GramInfo_Interace
    {
        return $this->graminfo;
    }

    public function getNotFoundWords()
    {
        return $this->notfound;
    }

    protected function createAnnotDecoder(phpMorphy_Morphier_Helper $helper): phpMorphy_AnnotDecoder_Common
    {
        return new phpMorphy_AnnotDecoder_Common($helper->getGramInfo()->getEnds());
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getAnnot($word): array
    {
        $result = [];

        foreach ($this->findWord($word) as $annot => $words) {
            $annot = $this->helper->decodeAnnot($annot, true);

            foreach ($words as $word) {
                $result[$word][] = $annot;
            }
        }

        return $result;
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getBaseForm($word): array
    {
        $annots = $this->findWord($word);

        return $this->composeForms($annots, true, false, false);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getAllForms($word): array
    {
        $annots = $this->findWord($word);

        return $this->composeForms($annots, false, false, false);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getPseudoRoot(string|array $word): array
    {
        $annots = $this->findWord($word);

        return $this->composeForms($annots, false, true, false);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getPartOfSpeech(array|string $word): array|string
    {
        $annots = $this->findWord($word);

        return $this->composeForms($annots, false, false, true);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function processAnnotsWithHelper($words, $method, $callWithWord = false): array
    {
        $result = [];

        foreach ($this->findWord($words) as $annot_raw => $words) {
            if ($GLOBALS['__phpmorphy_strlen']($annot_raw) == 0) {
                continue;
            }

            if ($callWithWord) {
                foreach ($words as $word) {
                    $result[$word] = $this->helper->$method($word, $annot_raw);
                }
            } else {
                $result_for_annot = $this->helper->$method($annot_raw);

                foreach ($words as $word) {
                    $result[$word] = $result_for_annot;
                }
            }
        }

        return $result;
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getAncode($word): array
    {
        return $this->processAnnotsWithHelper($word, 'getAncode');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getGrammarInfoMergeForms($word): array
    {
        return $this->processAnnotsWithHelper($word, 'getGrammarInfoMergeForms');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getGrammarInfo($word): array
    {
        return $this->processAnnotsWithHelper($word, 'getGrammarInfo');
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getAllFormsWithAncodes($word): array
    {
        return $this->processAnnotsWithHelper($word, 'getAllFormsWithResolvedAncodes', true);
    }

    /**
     * @throws phpMorphy_Exception
     */
    public function getWordDescriptor($word): array
    {
        return $this->processAnnotsWithHelper($word, 'getWordDescriptor', true);
    }

    /**
     * @throws phpMorphy_Exception
     */
    protected function findWord(array $words): array
    {
        $this->notfound = [];

        // Построение Patricia Trie
        [$labels, $finals, $dests] = $this->buildPatriciaTrie($words);

        $annots = [];
        $stack = [[0, '', $this->root_trans]]; // Используем массив для стека
        $fsa = $this->fsa;

        while (! empty($stack)) {
            [$n, $path, $trans] = array_pop($stack); // Берем последний элемент стека

            $currentPath = $path.$labels[$n];
            $isFinal = $finals[$n] > 0;

            // Обработка перехода по текущему узлу
            if ($trans !== false && $n > 0) {
                $label = $labels[$n];
                $result = $fsa->walk($trans, $label, $isFinal);

                if ($result['walked'] === $GLOBALS['__phpmorphy_strlen']($label)) {
                    $trans = $result['word_trans'];
                } else {
                    $trans = false;
                }
            }

            // Обработка конечного узла
            if ($isFinal) {
                if ($trans !== false && isset($result['annot'])) {
                    $annots[$result['annot']][] = $currentPath;
                } else {
                    $this->notfound[] = $currentPath;
                }
            }

            // Добавление дочерних узлов в стек
            if ($dests[$n] !== false) {
                foreach ($dests[$n] as $dest) {
                    $stack[] = [$dest, $currentPath, $trans]; // Добавляем в стек
                }
            }
        }

        return $annots;
    }

    protected function composeForms($annotsRaw, $onlyBase, $pseudoRoot, $partOfSpeech): array
    {
        $result = [];

        // process found annotations
        foreach ($annotsRaw as $annot_raw => $words) {
            if ($GLOBALS['__phpmorphy_strlen']($annot_raw) == 0) {
                continue;
            }

            foreach ($this->helper->decodeAnnot($annot_raw, $onlyBase) as $annot) {
                if (! ($onlyBase || $pseudoRoot)) {
                    $flexias = $this->graminfo->readFlexiaData($annot);
                }

                $cplen = $annot['cplen'];
                $plen = $annot['plen'];
                $flen = $annot['flen'];

                if ($partOfSpeech) {
                    $pos_id = $this->helper->extractPartOfSpeech($annot);
                }

                foreach ($words as $word) {
                    if ($flen) {
                        $base = $GLOBALS['__phpmorphy_substr']($word, $cplen + $plen, -$flen);
                    } else {
                        if ($cplen || $plen) {
                            $base = $GLOBALS['__phpmorphy_substr']($word, $cplen + $plen);
                        } else {
                            $base = $word;
                        }
                    }

                    $prefix = $cplen ? $GLOBALS['__phpmorphy_substr']($word, 0, $cplen) : '';

                    if ($pseudoRoot) {
                        $result[$word][$base] = 1;
                    } elseif ($onlyBase) {
                        $form = $prefix.$annot['base_prefix'].$base.$annot['base_suffix'];

                        $result[$word][$form] = 1;
                    } elseif ($partOfSpeech) {
                        $result[$word][$pos_id] = 1;
                    } else {
                        for ($i = 0, $c = count($flexias); $i < $c; $i += 2) {
                            $form = $prefix.$flexias[$i].$base.$flexias[$i + 1];
                            $result[$word][$form] = 1;
                        }
                    }
                }
            }
        }

        for ($keys = array_keys($result), $i = 0, $c = count($result); $i < $c; $i++) {
            $key = $keys[$i];

            $result[$key] = array_keys($result[$key]);
        }

        return $result;
    }

    /**
     * Строит Patricia Trie для массива слов.
     *
     * @param  string[]  $words  Массив слов.
     * @return array Возвращает массив с тремя элементами: метки узлов, флаги финальных узлов и переходы.
     *
     * @throws phpMorphy_Exception Если входные данные некорректны.
     */
    protected function buildPatriciaTrie(array $words): array
    {
        if (empty($words)) {
            throw new phpMorphy_Exception('Words must be a non-empty array');
        }

        // Сортируем слова для упрощения построения Trie
        sort($words);

        $stateLabels = [''];
        $stateFinals = '0';
        $stateDests = [[]];

        $stack = [];
        $prevWord = '';
        $prevWordLength = 0;
        $prevLcp = 0;

        foreach ($words as $word) {
            if ($word === $prevWord) {
                continue; // Пропускаем дубликаты
            }

            $wordLength = $GLOBALS['__phpmorphy_strlen']($word);
            $lcp = $this->calculateLongestCommonPrefix($word, $prevWord, $prevWordLength);

            if ($lcp === 0) {
                // Нет общего префикса, создаем новый узел
                $newStateId = count($stateLabels);
                $this->addNewNode($stateLabels, $stateFinals, $stateDests, $word, $newStateId);
                $stateDests[0][] = $newStateId;
                $node = $newStateId;
                $stack = [];
            } else {
                // Обработка случаев с общим префиксом
                $node = $this->handleCommonPrefix(
                    $lcp,
                    $prevLcp,
                    $prevWordLength,
                    $word,
                    $stateLabels,
                    $stateDests,
                    $stack,
                    $node
                );
            }

            // Обновляем предыдущие значения
            $prevWord = $word;
            $prevWordLength = $wordLength;
            $prevLcp = $lcp;
        }

        return [$stateLabels, $stateFinals, $stateDests];
    }

    /**
     * Добавляет новый узел в Trie.
     */
    private function addNewNode(array &$stateLabels, string &$stateFinals, array &$stateDests, string $label, int $newStateId): void
    {
        $stateLabels[] = $label;
        $stateFinals .= '1';
        $stateDests[] = false;
    }

    /**
     * Вычисляет длину наибольшего общего префикса (LCP) между двумя словами.
     */
    private function calculateLongestCommonPrefix(string $word, string $prevWord, int $prevWordLength): int
    {
        $maxLcp = min($prevWordLength, $this->getStringLength($word));
        for ($lcp = 0; $lcp < $maxLcp && $word[$lcp] === $prevWord[$lcp]; $lcp++);

        return $lcp;
    }

    /**
     * Обрабатывает случай, когда текущее слово имеет общий префикс с предыдущим.
     *
     * @throws phpMorphy_Exception
     */
    private function handleCommonPrefix(
        int $lcp,
        int $prevLcp,
        int $prevWordLength,
        string $word,
        array &$stateLabels,
        array &$stateDests,
        array &$stack,
        int $node
    ): int {
        if ($lcp === $prevLcp) {
            // Префикс совпадает с предыдущим, продолжаем с текущего узла
            return $stack[count($stack) - 1];
        }

        if ($lcp > $prevLcp) {
            // Новый префикс длиннее предыдущего
            if ($lcp === $prevWordLength) {
                return $node; // Не нужно разделять
            }
            $stack[] = $node;
            $trimSize = $lcp - $prevLcp;
        } else {
            // Новый префикс короче предыдущего
            $trimSize = $prevWordLength - $lcp;
            for ($stackSize = count($stack) - 1; ; $stackSize--) {
                $trimSize -= $this->getStringLength($stateLabels[$node]);
                if ($trimSize <= 0) {
                    break;
                }
                if (empty($stack)) {
                    throw new phpMorphy_Exception('Infinite loop possible');
                }
                $node = array_pop($stack);
            }
            $trimSize = abs($trimSize);
        }

        // Разделяем узел, если необходимо
        if ($trimSize > 0) {
            $node = $this->splitNode($node, $trimSize, $word, $lcp, $stateLabels, $stateFinals, $stateDests, $stack);
        }

        return $node;
    }

    /**
     * Разделяет узел на два новых узла.
     */
    private function splitNode(
        int $node,
        int $trimSize,
        string $word,
        int $lcp,
        array &$stateLabels,
        string &$stateFinals,
        array &$stateDests,
    ): int {
        $nodeKey = $stateLabels[$node];

        // Создаем первый новый узел
        $newNodeId1 = count($stateLabels);
        $this->addNewNode($stateLabels, $stateFinals, $stateDests, $this->getSubstring($nodeKey, $trimSize), $newNodeId1);

        // Обновляем текущий узел
        $stateLabels[$node] = $this->getSubstring($nodeKey, 0, $trimSize);
        $stateFinals[$node] = '0';
        $stateDests[$node] = [$newNodeId1];

        // Создаем второй новый узел
        $newNodeId2 = $newNodeId1 + 1;
        $this->addNewNode($stateLabels, $stateFinals, $stateDests, $this->getSubstring($word, $lcp), $newNodeId2);
        $stateDests[$node][] = $newNodeId2;

        return $newNodeId2;
    }

    /**
     * Возвращает подстроку с учетом многобайтовых символов.
     */
    private function getSubstring(string $str, int $start, ?int $length = null): string
    {
        return mb_substr($str, $start, $length, 'UTF-8');
    }

    /**
     * Возвращает длину строки с учетом многобайтовых символов.
     */
    private function getStringLength(string $str): int
    {
        return mb_strlen($str, 'UTF-8');
    }
}
