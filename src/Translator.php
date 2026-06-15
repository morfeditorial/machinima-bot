<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is licensed under the CSSM Unlimited License v2.0.
 * Copyright (c) 2024 Sergiy Chernega
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial;

class Translator
{
    private $translations;

    private $user_locale;

    /**
     * Constructor to initialize translations and user locale.
     *
     * @param array  $translations An associative array of translations.
     * @param string $user_locale  The locale to be used for translations.
     */
    public function __construct(array $translations, string $user_locale)
    {
        $this->translations = $translations;
        $this->user_locale = $user_locale;
    }

    /**
     * Translate a given key to the user's locale.
     *
     * @param  string $key The key for the translation.
     * @return mixed  The translated string, array, or a not found message.
     */
    public function translate(string $key)
    {
        if (isset($this->translations[$key])) {
            if (isset($this->translations[$key][$this->user_locale])) {
                return $this->translations[$key][$this->user_locale];
            } elseif (isset($this->translations[$key]['en'])) {
                return $this->translations[$key]['en'];
            }
        }

        return "Translation not found for key: $key";
    }

    /**
     * Sets the user's locale used for translations.
     *
     * @param string $locale The user's locale in IETF BCP 47 format (e.g., 'uk' for Ukrainian, 'en' for English).
     */
    public function setUserLocale(string $locale) : void
    {
        $this->user_locale = $locale;
    }

    /**
     * Returns the user's locale used for translations.
     *
     * @return string The user's locale in IETF BCP 47 format (e.g., 'uk' for Ukrainian, 'en' for English).
     */
    public function getUserLocale() : string
    {
        return $this->user_locale;
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
