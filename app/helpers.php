<?php
// app/helpers.php

use App\Helpers\RefereeLevelsHelper;

if (!function_exists('referee_levels')) {
    function referee_levels(bool $includeArchived = false): array
    {
        return RefereeLevelsHelper::getSelectOptions($includeArchived);
    }
}

if (!function_exists('normalize_referee_level')) {
    function normalize_referee_level(?string $level): ?string
    {
        return RefereeLevelsHelper::normalize($level);
    }
}

if (!function_exists('referee_level_label')) {
    function referee_level_label(?string $level): string
    {
        return RefereeLevelsHelper::getLabel($level);
    }
}
