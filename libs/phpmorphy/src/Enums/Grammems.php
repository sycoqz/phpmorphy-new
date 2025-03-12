<?php

namespace enums;

enum Grammems: string
{
    // Падежи
    case NOMINATIVE = 'ИМ'; // Именительный падеж
    case GENITIVE = 'РД'; // Родительный падеж
    case DATIVE = 'ДТ'; // Дательный падеж
    case ACCUSATIVE = 'ВН'; // Винительный падеж
    case INSTRUMENTAL = 'ТВ'; // Творительный падеж
    case PREPOSITIONAL = 'ПР'; // Предложный падеж

    // Число
    case SINGULAR = 'ЕД'; // Единственное число
    case PLURAL = 'МН'; // Множественное число

    // Род
    case MASCULINE = 'МР'; // Мужской род
    case FEMININE = 'ЖР'; // Женский род
    case NEUTER = 'СР'; // Средний род

    // Время (для глаголов)
    case PRESENT = 'НСТ'; // Настоящее время
    case PAST = 'ПРШ'; // Прошедшее время
    case FUTURE = 'БУД'; // Будущее время

    // Залог (для глаголов)
    case ACTIVE = 'ДСТ'; // Действительный залог
    case PASSIVE = 'СТР'; // Страдательный залог

    // Другие грамматические характеристики
    case SHORT = 'КР'; // Краткая форма (для прилагательных и причастий)
    case COMPARATIVE = 'СРАВН'; // Сравнительная степень
    case SUPERLATIVE = 'ПРЕВ'; // Превосходная степень
    case IMPERATIVE = 'ПВЛ'; // Повелительное наклонение
    case INFINITIVE = 'ИНФ'; // Инфинитив
}
