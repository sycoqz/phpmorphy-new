# sycoqz/phpmorphy-new, The fork of cijic/phpmorphy

phpMorphy --- morphological analyzer library for Russian, English, German and Ukrainian languages.  
```sycoqz/phpmorphy-new``` is Laravel wrapper for phpMorphy library with PHP8 support.

Source website (in russian): http://phpmorphy.sourceforge.net/  
SF project: http://sourceforge.net/projects/phpmorphy  
Main wrapper on Github: https://github.com/cijic/phpmorphy
Fork: https://github.com/sycoqz/phpmorphy-new

This library allow retireve follow morph information for any word:
- Base (normal) form
- All forms
- Grammatical (part of speech, grammems) information

## Install

Via Composer
``` bash
$ composer require cijic/phpmorphy
```

## Usage
```php
$morphy = new cijic\phpMorphy\Morphy('en');
print_r($morphy->getPseudoRoot('FIGHTY'));

// result:
// Array
// (
//   [0] => FIGHTY
//   [1] => FIGHT
// )
```

### Get all the forms of the words
```php
$morphy = new cijic\phpMorphy\Morphy('ru');
$morphy->getAllForms('ДОМ');
// result:
// Array
// (
//   [0] => ДОМУ
//   [1] => ДОМАМ
// )
```

### Get the plural form of the word
```php
$morphy = new cijic\phpMorphy\Morphy('ru');
$morphy->getPluralForm('ОТЕЛЬ');
// result:
// string('ОТЕЛИ')
```

### Get word by part of speech and grammems
```php
// Падежы / grammes
// 'Именительный' => 'ИМ',
// 'Родительный' =>'РД',
// 'Дательный' => 'ДТ',
// 'Винительный' => 'ВН',
// 'Творительный' => 'ТВ',
// 'Предложный' => 'ПР',
$morphy = new cijic\phpMorphy\Morphy('ru');
$morphy->castFormByGramInfo('ДОМ', 'С', ['ДТ'], true);
// result:
// (
//   [0] => ДОМУ
//   [1] => ДОМАМ
// )
```

## Laravel support
### Facade
``` php
Morphy::getPseudoRoot('БОЙЦОВЫЙ')
```

### Add russian facade support

Add to config/app.php:

Section ```providers```
``` php
cijic\phpMorphy\MorphyServiceProvider::class,
```

Section ```aliases```
``` php
'Morphy'    => cijic\phpMorphy\Facade\Morphy::class,
```
