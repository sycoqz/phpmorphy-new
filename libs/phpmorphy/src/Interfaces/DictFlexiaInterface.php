<?php

namespace Interfaces;

interface DictFlexiaInterface
{
    function getPrefix() : string;
    function getSuffix() : string;
    function getAncodeId() : int;
    function setPrefix(string $prefix) : void;
}
