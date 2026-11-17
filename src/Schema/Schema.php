<?php

namespace Antlion\SilverwindSite\Schema;

final class Schema
{
    public static function json(array $graph): string
    {
        return json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_HEX_TAG);
    }
    public static function hasData(array $node): bool
    {
        foreach ($node as $v) {
            if ($v !== null && $v !== '' && $v !== []) {
                return true;
            }
        }
        return false;
    }
}
