<?php

namespace enums;

/**
 * Enum доступных вариантов частей речи
 */
enum PartOfSpeech: string
{
    /**
     * Существительное
     */
    case NOUN = 'С';

    /**
     * Полное прилагательное
     */
    case ADJECTIVE_FULL = 'П';

    /**
     * Краткое прилагательное
     */
    case ADJECTIVE_SHORT = 'КР_ПРИЛ';

    /**
     * Компаратив (Сравнительная степень)
     */
    case COMPARATIVE = 'КОМП';

    /**
     * Глагол (личная форма)
     */
    case VERB = 'Г';

    /**
     * Инфинитив (неопределенная форма глагола)
     */
    case INFINITIVE = 'ИНФ';

    /**
     * Полное причастие
     */
    case PARTICIPLE_FULL = 'ПРИЧ';

    /**
     * Краткое причастие
     */
    case PARTICIPLE_SHORT = 'КР_ПРИЧ';

    /**
     * Деепричастие
     */
    case GERUND = 'ДЕЕПР';

    /**
     * Числительное (количественное)
     */
    case NUMERAL = 'ЧИСЛ';

    /**
     * Числительное (порядковое)
     */
    case NUMERAL_ORDINAL = 'ЧИСЛ-П';

    /**
     * Наречие
     */
    case ADVERB = 'Н';

    /**
     * Местоимение
     */
    case PRONOUN = 'МС';

    /**
     * Местоимение-прилагательное
     */
    case PRONOUN_ADJECTIVE = 'МС-П';

    /**
     * Местоимение-предикатив
     */
    case PRONOUN_PREDICATIVE = 'МС-ПРЕДК';

    /**
     * Предикатив
     */
    case PREDICATIVE = 'ПРЕДК';

    /**
     * Предлог
     */
    case PREPOSITION = 'ПР';

    /**
     * Союз
     */
    case CONJUNCTION = 'СОЮЗ';

    /**
     * Частица
     */
    case PARTICLE = 'ЧАСТ';

    /**
     * Междометие
     */
    case INTERJECTION = 'МЕЖД';

    /**
     * Вводное слово
     */
    case INTRODUCTORY_WORD = 'ВВОДН';

    /**
     * Фразеологизм
     */
    case PHRASE = 'ФРАЗ';

    /**
     * Часть сложного слова
     */
    case PART_OF_COMPOUND_WORD = 'ЧАСТ-СЛОВА';

    /**
     * Другое (неопределенная часть речи)
     */
    case OTHER = 'ДР';
}
