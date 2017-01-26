<?php

class DataGrid_Tools {
    /**
     * Pad a string on left, right, or either side. While this seems like something
     * str_pad() could do, it can't. We might be padding strings with ANSI sequences
     * that shouldn't be taken into account of the string length when padding it.
     *
     * @param   string  $str
     * @param   integer $pad_len    Number of characters to pad
     * @param   integer $pad_align
     */
    static function strPad($str, $pad_len, $pad_align) {
        switch ($pad_align) {
            case STR_PAD_LEFT:  return str_repeat(" ", $pad_len) . $str;
            case STR_PAD_RIGHT: return $str . str_repeat(" ", $pad_len);
            case STR_PAD_BOTH:  return str_repeat(" ", (int)floor($pad_len / 2)) . $str . str_repeat(" ", (int)ceil($pad_len / 2));
        }
    }

    /**
     * Get the length of a string, with ANSI escape sequences removed.
     *
     * @param mixed $str
     */
    static function strLen($str)
    {
        if (!is_string($str)) {
            $str = (string)$str;
        }

        return mb_strlen(static::removeANSISequences($str));
    }

    /**
     * Returns a substring
     *
     * @param mixed $str
     */
    static function substr(string $str, int $start, int $length)
    {
        $str = static::removeANSISequences($str);

        return mb_substr($str, $start, $length);
    }

    /**
     * Removes all ANSI sequences from given string.
     *
     * @param  string $str
     * @return string
     */
    static function removeANSISequences(string $str): string
    {
        return preg_replace("/(\x9B|\x1B\[)[0-?]*[ -\/]*[@-~]/", "", $str);
    }
}
