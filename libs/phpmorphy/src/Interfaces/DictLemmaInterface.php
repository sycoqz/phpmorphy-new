<?php

namespace Interfaces;

interface DictLemmaInterface
{
    public function setPrefixId(?int $prefixId): void;

    public function setAncodeId(?int $ancodeId): void;

    public function getBase(): string;

    public function getFlexiaId(): int;

    public function getAccentId(): int;

    public function getPrefixId(): int;

    public function getAncodeId(): int;

    public function hasPrefixId(): bool;

    public function hasAncodeId(): bool;
}
