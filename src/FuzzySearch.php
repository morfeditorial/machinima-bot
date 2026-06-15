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
        $transliteration_table = [
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
        return strtr($string, $transliteration_table);
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
        $fields_to_search = ["name", "biography", "link"];
        $content_fields_to_search = ["title", "description"];

        $query = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $query)));
        $query_words = explode(" ", $query);

        foreach ($fields_to_search as $field) {
            if (!isset($item[$field])) {
                continue; // Skip if the field does not exist
            }

            $field_value = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $item[$field])));

            similar_text($field_value, $query, $text_similarity);

            if ($text_similarity >= 40) {
                return $text_similarity;
            }

            if (false !== mb_strpos($field_value, $query)) {
                return 100;
            }

            similar_text($field_value, $query, $similarity);
            if ($similarity >= 60) {
                return $similarity;
            }
        }

        foreach ($content_fields_to_search as $field) {
            if (!isset($item["content"][$field])) {
                continue;
            }

            $content_value = $this->transliterate(trim(preg_replace(["/[^\p{L}\p{N}\s]+/u", "/\s+/u"], " ", $item["content"][$field])));

            if (false !== mb_strpos($content_value, $query)) {
                return 100;
            }

            similar_text($content_value, $query, $similarity);
            if ($similarity >= 60) {
                return $similarity;
            }
        }

        return false;
    }

    public function checkPartialMatch($query, $text) : bool
    {
        $query_words = explode(" ", $query);

        foreach ($query_words as $word) {
            if (false === mb_stripos($text, $word)) {
                return false;
            }
        }

        return true;
    }
}
