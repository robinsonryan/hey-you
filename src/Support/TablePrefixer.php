<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

final class TablePrefixer
{
    public static function prefix(string $table): string
    {
        $prefix = config('heyyou.table_prefix', 'heyyou_');

        if ($prefix === null || $prefix === '') {
            return $table;
        }

        return $prefix.$table;
    }
}
