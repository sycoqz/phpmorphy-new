<?php

namespace Interfaces;

interface GramInfoInterface
{
    /**
     * Returns langugage for graminfo file
     */
    public function getLocale(): string;

    /**
     * Return encoding for graminfo file
     */
    public function getEncoding(): string;

    /**
     * Return size of character (cp1251 - 1, utf8 - 1, utf16 - 2, utf32 - 4 etc)
     */
    public function getCharSize(): int;

    /**
     * Return end of string value (usually string with \0 value of char_size + 1 length)
     */
    public function getEnds(): string;

    /**
     * Reads graminfo header
     */
    public function readGramInfoHeader(int $offset): array|bool;

    /**
     * Returns size of header struct
     */
    public function getGramInfoHeaderSize();

    /**
     * Read ancodes section for header retrieved with readGramInfoHeader
     */
    public function readAncodes(array $info): array;

    /**
     * Read flexias section for header retrieved with readGramInfoHeader
     */
    public function readFlexiaData(array $info): array;

    /**
     * Read all graminfo headers offsets, which can be used latter for readGramInfoHeader method
     */
    public function readAllGramInfoOffsets(): array;

    public function getHeader();

    public function readAllPartOfSpeech();

    public function readAllGrammems();

    public function readAllAncodes();
}
