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
 * Copyright (c) 2024 Serhii Cherneha
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial;

class FuzzySearch
{
    public function __construct() {}

    public function transliterate(string $string) : string
    {
        $transliterationTable = [
            "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d",
            "е" => "e", "ё" => "e", "ж" => "zh", "з" => "z", "и" => "i",
            "й" => "y", "к" => "k", "л" => "l", "м" => "m", "н" => "n",
            "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t",
            "у" => "u", "ф" => "f", "х" => "kh", "ц" => "ts", "ч" => "ch",
            "ш" => "sh", "щ" => "sch", "ь" => "'", "ы" => "y", "ъ" => "'",
            "э" => "e", "ю" => "yu", "я" => "ya",
            "ґ" => "g", "є" => "ye", "і" => "i", "ї" => "yi"
        ];

        $string = mb_strtolower($string, "utf-8");
        return strtr($string, $transliterationTable);
    }

    public function fuzzySearch($query, $data) : array
    {
        $results = [];

        foreach ($data as $item) {
            $similarity = $this->isMatch($query, $item);

            if (false !== $similarity) {
                $item["similarity"] = $similarity;
                $results[] = $item;
            }
        }

        usort($results, function ($a, $b) {
            return $b["similarity"] <=> $a["similarity"];
        });

        return $results;
    }

    public function isMatch($query, $item)
    {
        $fieldsToSearch = ["name", "biography", "link"];
        $contentFieldsToSearch = ["title", "description"];

        $query = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $query)));
        $queryWords = explode(" ", $query);

        foreach ($fieldsToSearch as $field) {
            if (!isset($item[$field])) {
                continue; // Skip if the field does not exist
            }

            $fieldValue = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $item[$field])));

            similar_text($fieldValue, $query, $textSimilarity);

            if ($textSimilarity >= 40) {
                return $textSimilarity;
            }

            if (false !== mb_strpos($fieldValue, $query)) {
                return 100;
            }

            similar_text($fieldValue, $query, $similarity);
            if ($similarity >= 60) {
                return $similarity;
            }
        }

        foreach ($contentFieldsToSearch as $field) {
            if (!isset($item["content"][$field])) {
                continue;
            }

            $contentValue = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $item["content"][$field])));

            if (false !== mb_strpos($contentValue, $query)) {
                return 100;
            }

            similar_text($contentValue, $query, $similarity);
            if ($similarity >= 60) {
                return $similarity;
            }
        }

        return false;
    }

    public function checkPartialMatch($query, $text) : bool
    {
        $queryWords = explode(" ", $query);

        foreach ($queryWords as $word) {
            if (false === mb_stripos($text, $word)) {
                return false;
            }
        }

        return true;
    }
}
