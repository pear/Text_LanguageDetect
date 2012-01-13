<?php
/**
 * Part of Text_LanguageDetect
 *
 * PHP version 5
 *
 * @category  Text
 * @package   Text_LanguageDetect
 * @author    Christian Weiske <cweiske@php.net>
 * @copyright 2011 Christian Weiske <cweiske@php.net>
 * @license   http://www.debian.org/misc/bsd.license BSD
 * @version   SVN: $Id$
 * @link      http://pear.php.net/package/Text_LanguageDetect/
 */

/**
 * Provides a mapping between the languages from lang.dat and the
 * ISO 639-1 and ISO-639-2 codes.
 *
 * @category  Text
 * @package   Text_LanguageDetect
 * @author    Christian Weiske <cweiske@php.net>
 * @copyright 2011 Christian Weiske <cweiske@php.net>
 * @license   http://www.debian.org/misc/bsd.license BSD
 * @link      http://pear.php.net/package/Text_LanguageDetect/
 */
class Text_LanguageDetect_ISO639
{
    /**
     * Maps all language names from the language database to the
     * ISO 639-1 2-letter language code.
     *
     * NULL indicates that there is no 2-letter code.
     *
     * @var array
     */
    public static $name2code2 = array(
        'albanian'   => 'sq',
        'arabic'     => 'ar',
        'azeri'      => 'az',
        'bengali'    => 'bn',
        'bulgarian'  => 'bg',
        'cebuano'    => null,
        'croatian'   => 'hr',
        'czech'      => 'cs',
        'danish'     => 'da',
        'dutch'      => 'nl',
        'english'    => 'en',
        'estonian'   => 'et',
        'farsi'      => 'fa',
        'finnish'    => 'fi',
        'french'     => 'fr',
        'german'     => 'de',
        'hausa'      => 'ha',
        'hawaiian'   => null,
        'hindi'      => 'hi',
        'hungarian'  => 'hu',
        'icelandic'  => 'is',
        'indonesian' => 'id',
        'italian'    => 'it',
        'kazakh'     => 'kk',
        'kyrgyz'     => 'ky',
        'latin'      => 'la',
        'latvian'    => 'lv',
        'lithuanian' => 'lt',
        'macedonian' => 'mk',
        'mongolian'  => 'mn',
        'nepali'     => 'ne',
        'norwegian'  => 'no',
        'pashto'     => 'ps',
        'pidgin'     => null,
        'polish'     => 'pl',
        'portuguese' => 'pt',
        'romanian'   => 'ro',
        'russian'    => 'ru',
        'serbian'    => 'sr',
        'slovak'     => 'sk',
        'slovene'    => 'sl',
        'somali'     => 'so',
        'spanish'    => 'es',
        'swahili'    => 'sw',
        'swedish'    => 'sv',
        'tagalog'    => 'tl',
        'turkish'    => 'tr',
        'ukrainian'  => 'uk',
        'urdu'       => 'ur',
        'uzbek'      => 'uz',
        'vietnamese' => 'vi',
        'welsh'      => 'cy',
    );

    /**
     * Maps all language names from the language database to the
     * ISO 639-2 3-letter language code.
     *
     * @var array
     */
    public static $name2code3 = array(
        'albanian'   => 'sqi',
        'arabic'     => 'ara',
        'azeri'      => 'aze',
        'bengali'    => 'ben',
        'bulgarian'  => 'bul',
        'cebuano'    => 'ceb',
        'croatian'   => 'hrv',
        'czech'      => 'ces',
        'danish'     => 'dan',
        'dutch'      => 'nld',
        'english'    => 'eng',
        'estonian'   => 'est',
        'farsi'      => 'fas',
        'finnish'    => 'fin',
        'french'     => 'fra',
        'german'     => 'deu',
        'hausa'      => 'hau',
        'hawaiian'   => 'haw',
        'hindi'      => 'hin',
        'hungarian'  => 'hun',
        'icelandic'  => 'isl',
        'indonesian' => 'ind',
        'italian'    => 'ita',
        'kazakh'     => 'kaz',
        'kyrgyz'     => 'kir',
        'latin'      => 'lat',
        'latvian'    => 'lav',
        'lithuanian' => 'lit',
        'macedonian' => 'mkd',
        'mongolian'  => 'mon',
        'nepali'     => 'nep',
        'norwegian'  => 'nor',
        'pashto'     => 'pus',
        'pidgin'     => 'crp',
        'polish'     => 'pol',
        'portuguese' => 'por',
        'romanian'   => 'ron',
        'russian'    => 'rus',
        'serbian'    => 'srp',
        'slovak'     => 'slk',
        'slovene'    => 'slv',
        'somali'     => 'som',
        'spanish'    => 'spa',
        'swahili'    => 'swa',
        'swedish'    => 'swe',
        'tagalog'    => 'tgl',
        'turkish'    => 'tur',
        'ukrainian'  => 'ukr',
        'urdu'       => 'urd',
        'uzbek'      => 'uzb',
        'vietnamese' => 'vie',
        'welsh'      => 'cym',
    );

}

?>