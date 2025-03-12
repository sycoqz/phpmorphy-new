<?php

namespace Interfaces;

interface MorphierInterface
{
    public function getAnnot($word);
    public function getBaseForm($word);
    public function getAllForms($word);
    public function getPseudoRoot(string|array $word);
    public function getPartOfSpeech(string|array $word);
    public function getWordDescriptor($word);
    public function getAllFormsWithAncodes($word);
    public function getAncode($word);
    public function getGrammarInfoMergeForms($word);
    public function getGrammarInfo($word);
}
