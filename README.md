# cijic/phpmorphy

[![Packagist Downloads](https://img.shields.io/packagist/dt/cijic/phpmorphy.svg)](https://packagist.org/packages/cijic/phpmorphy)

phpMorphy --- morphological analyzer library for Russian, English, German and Ukrainian languages.  
```cijic/phpMorphy``` is Laravel wrapper for phpMorphy library with PHP7 support.

Source website (in russian): http://phpmorphy.sourceforge.net/  
SF project: http://sourceforge.net/projects/phpmorphy  
Wrapper on Github: https://github.com/cijic/phpmorphy

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
```
result 
```
Array
(
    [0] => FIGHTY
    [1] => FIGHT
)
```

```php
// Падежы / grammes
// 'Именительный' => 'ИМ',
// 'Родительный' =>['РД',
// 'Дательный' => 'ДТ',
// 'Винительный' => 'ВН',
// 'Творительный' => 'ТВ',
// 'Предложный' => 'ПР',
$morphy = new cijic\phpMorphy\Morphy('ru');
$morphy->castFormByGramInfo('ДОМ', 'С', ['ДТ'], true);
```
result 
```
Array
(
    [0] => ДОМУ
    [1] => ДОМАМ
)
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
## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security related issues, please email altcode@ya.ru instead of using the issue tracker.

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
