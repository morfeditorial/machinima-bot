<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 */

declare(strict_types=1);

namespace morfeditorial;

class Translator
{
    private $translations;

    private $userLocale;

    /**
     * Constructor to initialize translations and user locale.
     *
     * @param  array  $translations  An associative array of translations.
     * @param  string  $userLocale  The locale to be used for translations.
     */
    public function __construct($translations, $userLocale)
    {
        $this->translations = $translations;
        $this->userLocale = $userLocale;
    }

    /**
     * Translate a given key to the user's locale.
     *
     * @param  string  $key  The key for the translation.
     * @return mixed The translated string, array, or a not found message.
     */
    public function translate($key)
    {
        if (isset($this->translations[$key])) {
            if (isset($this->translations[$key][$this->userLocale])) {
                return $this->translations[$key][$this->userLocale];
            } elseif (isset($this->translations[$key]['en'])) {
                return $this->translations[$key]['en'];
            }
        }

        return "Translation not found for key: $key";
    }

    /**
     * Sets the user's locale used for translations.
     *
     * @param  string  $locale  The user's locale in IETF BCP 47 format (e.g., 'uk' for Ukrainian, 'en' for English).
     */
    public function setUserLocale(string $locale) : void
    {
        $this->userLocale = $locale;
    }

    /**
     * Returns the user's locale used for translations.
     *
     * @return string The user's locale in IETF BCP 47 format (e.g., 'uk' for Ukrainian, 'en' for English).
     */
    public function getUserLocale() : string
    {
        return $this->userLocale;
    }

    /**
     * Returns all available locales in the translations.
     *
     * @return array An array of available locales.
     */
    public function getAvailableLocales() : array
    {
        $locales = [];
        foreach ($this->translations as $key => $translations) {
            foreach ($translations as $locale => $translation) {
                if (! in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
        }

        return $locales;
    }
}
