<?php

if (! function_exists('mb_split')) {
    /**
     * Pengganti mb_split() untuk runtime PHP tanpa mbregex (Wasmer).
     */
    function mb_split(string $pattern, string $string, int $limit = -1): array
    {
        $result = @preg_split('~'.str_replace('~', '\~', $pattern).'~u', $string, $limit);

        return $result === false ? [$string] : $result;
    }
}