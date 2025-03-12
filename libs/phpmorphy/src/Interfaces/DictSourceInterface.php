<?php

namespace Interfaces;

use Iterator;

interface DictSourceInterface
{
    public function getName(): string;

    /**
     * ISO3166 country code separated by underscore(_) from ISO639 language code
     * ru_RU, uk_UA for example
     */
    public function getLanguage(): string;

    /**
     * Any string
     */
    public function getDescription(): string;

    /**
     * @return Iterator over objects of phpMorphy_Dict_Ancode
     */
    public function getAncodes(): Iterator;

    /**
     * @return Iterator over objects of phpMorphy_Dict_FlexiaModel
     */
    public function getFlexias(): Iterator;

    /**
     * @return Iterator over objects of phpMorphy_Dict_PrefixSet
     */
    public function getPrefixes(): Iterator;

    /**
     * @return Iterator over objects of phpMorphy_Dict_AccentModel
     */
    public function getAccents(): Iterator;

    /**
     * @return Iterator over objects of phpMorphy_Dict_Lemma
     */
    public function getLemmas(): Iterator;
}
