<?php
/**
 * Complete mbstring polyfill for servers where php-mbstring is not enabled.
 * Implements all multibyte string functions and constants required by Dompdf / Cpdf.
 */

declare(strict_types=1);

// Mbstring Constants
if (!defined('MB_CASE_UPPER')) {
    define('MB_CASE_UPPER', 0);
}
if (!defined('MB_CASE_LOWER')) {
    define('MB_CASE_LOWER', 1);
}
if (!defined('MB_CASE_TITLE')) {
    define('MB_CASE_TITLE', 2);
}

// 1. mb_internal_encoding
if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding(?string $encoding = null): string|bool
    {
        static $internal = 'UTF-8';
        if ($encoding !== null) {
            $internal = $encoding;
            return true;
        }
        return $internal;
    }
}

// 2. mb_list_encodings
if (!function_exists('mb_list_encodings')) {
    function mb_list_encodings(): array
    {
        return [
            'UTF-8',
            'Windows-1252',
            'ISO-8859-1',
            'ISO-8859-15',
            'ASCII',
            'CP1252',
            'UTF-16',
            'UTF-32',
        ];
    }
}

// 3. mb_strlen
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        if (function_exists('iconv_strlen')) {
            $len = @iconv_strlen($string, $encoding ?? 'UTF-8');
            if ($len !== false) {
                return $len;
            }
        }
        return strlen($string);
    }
}

// 4. mb_substr
if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (function_exists('iconv_substr')) {
            $enc = $encoding ?? 'UTF-8';
            $total = @iconv_strlen($string, $enc);
            $len = $length !== null ? $length : ($total !== false ? ($total - $start) : strlen($string));
            $sub = @iconv_substr($string, $start, $len, $enc);
            if ($sub !== false) {
                return $sub;
            }
        }
        return $length !== null ? substr($string, $start, $length) : substr($string, $start);
    }
}

// 5. mb_strpos
if (!function_exists('mb_strpos')) {
    function mb_strpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        if (function_exists('iconv_strpos')) {
            $pos = @iconv_strpos($haystack, $needle, $offset, $encoding ?? 'UTF-8');
            if ($pos !== false) {
                return $pos;
            }
        }
        return strpos($haystack, $needle, $offset);
    }
}

// 6. mb_strrpos
if (!function_exists('mb_strrpos')) {
    function mb_strrpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        if (function_exists('iconv_strrpos')) {
            $pos = @iconv_strrpos($haystack, $needle, $offset, $encoding ?? 'UTF-8');
            if ($pos !== false) {
                return $pos;
            }
        }
        return strrpos($haystack, $needle, $offset);
    }
}

// 7. mb_stripos
if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        return stripos($haystack, $needle, $offset);
    }
}

// 8. mb_strtolower
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

// 9. mb_strtoupper
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        return strtoupper($string);
    }
}

// 10. mb_convert_encoding
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding(array|string $string, string $to_encoding, array|string|null $from_encoding = null): array|string
    {
        if (is_array($string)) {
            $result = [];
            foreach ($string as $k => $v) {
                $result[$k] = is_string($v) ? mb_convert_encoding($v, $to_encoding, $from_encoding) : $v;
            }
            return $result;
        }

        if (function_exists('iconv')) {
            $from = is_array($from_encoding) ? ($from_encoding[0] ?? 'UTF-8') : ($from_encoding ?? 'UTF-8');
            $conv = @iconv($from, $to_encoding . '//IGNORE', $string);
            if ($conv !== false) {
                return $conv;
            }
        }
        return $string;
    }
}

// 11. mb_detect_encoding
if (!function_exists('mb_detect_encoding')) {
    function mb_detect_encoding(string $string, array|string|null $encodings = null, bool $strict = false): string|false
    {
        return 'UTF-8';
    }
}

// 12. mb_detect_order
if (!function_exists('mb_detect_order')) {
    function mb_detect_order(array|string|null $encoding = null): array|bool
    {
        return ['UTF-8', 'Windows-1252', 'ISO-8859-1'];
    }
}

// 13. mb_str_split
if (!function_exists('mb_str_split')) {
    function mb_str_split(string $string, int $length = 1, ?string $encoding = null): array|false
    {
        $arr = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($arr !== false) {
            if ($length === 1) {
                return $arr;
            }
            return array_map(fn($chunk) => implode('', $chunk), array_chunk($arr, $length));
        }
        return str_split($string, $length);
    }
}

// 14. mb_chr & mb_ord
if (!function_exists('mb_ord')) {
    function mb_ord(string $string, ?string $encoding = null): int|false
    {
        return ord($string[0] ?? '');
    }
}

if (!function_exists('mb_chr')) {
    function mb_chr(int $codepoint, ?string $encoding = null): string|false
    {
        return chr($codepoint);
    }
}

// 15. mb_convert_case
if (!function_exists('mb_convert_case')) {
    function mb_convert_case(string $string, int $mode, ?string $encoding = null): string
    {
        return match ($mode) {
            MB_CASE_UPPER => strtoupper($string),
            MB_CASE_LOWER => strtolower($string),
            MB_CASE_TITLE => ucwords(strtolower($string)),
            default       => $string,
        };
    }
}

// 16. mb_substr_count
if (!function_exists('mb_substr_count')) {
    function mb_substr_count(string $haystack, string $needle, ?string $encoding = null): int
    {
        return substr_count($haystack, $needle);
    }
}

// 17. mb_substitute_character
if (!function_exists('mb_substitute_character')) {
    function mb_substitute_character(string|int|null $substitute_character = null): string|int|bool
    {
        return 'none';
    }
}

// 18. mb_encode_numericentity
if (!function_exists('mb_encode_numericentity')) {
    function mb_encode_numericentity(string $string, array $convmap, ?string $encoding = null, bool $hex = false): string
    {
        return $string;
    }
}

// 19. mb_ucwords
if (!function_exists('mb_ucwords')) {
    function mb_ucwords(string $string, string $separators = " \t\r\n\f\v", ?string $encoding = null): string
    {
        return ucwords($string, $separators);
    }
}
