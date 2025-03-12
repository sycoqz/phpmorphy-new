<?php

namespace Interfaces;

interface GramTabConstHelperInterface
{
    public function getPartOfSpeechIdByName($name);

    public function getGrammemIdByName($name);

    public function getGrammemShiftByName($name);

    public function hasGrammemName($name);

    public function hasPartOfSpeechName($name);

    public function getGrammemsConsts();

    public function getPosesConsts();
}
