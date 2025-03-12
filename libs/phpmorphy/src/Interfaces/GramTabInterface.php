<?php

namespace Interfaces;

interface GramTabInterface
{
    public function getGrammems(int $ancodeId);
    public function getPartOfSpeech(int $ancodeId);
    public function resolveGrammemIds(array|int $ids);
    public function resolvePartOfSpeechId($id);
    public function includeConsts();
    public function ancodeToString($ancodeId, $commonAncode = null);
    public function stringToAncode($string);
    public function toString($partOfSpeechId, $grammemIds);
}
