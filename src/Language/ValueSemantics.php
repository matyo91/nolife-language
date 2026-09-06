<?php

declare(strict_types=1);

namespace App\Language;

/**
 * Tiny demonstration that PHP values, PHP references, and C pointers
 * are three different things. No FFI.
 *
 * Teaching map (php-src / Nikic):
 *   - a zval is a type tag + value
 *   - strings and arrays are copy-on-write
 *   - objects are a handle to a zend_object
 *   - `&` wraps a zval in IS_REFERENCE / zend_reference
 *   - that is not a C pointer the user can increment
 */
final class ValueSemantics
{
    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function demonstrate(BenchmarkState $state): array
    {
        $rows = [];

        $word = 'cost';
        $copy = $word;
        $copy = 'investment';
        $rows[] = [
            'label' => 'string assignment',
            'detail' => sprintf(
                'copying $word does not alias it (copy-on-write zval). $word=%s $copy=%s',
                $word,
                $copy,
            ),
        ];

        $counts = ['tokens' => 1];
        $countsCopy = $counts;
        $countsCopy['tokens'] = 2;
        $rows[] = [
            'label' => 'array assignment',
            'detail' => sprintf(
                'arrays separate on write. original=%d copy=%d',
                $counts['tokens'],
                $countsCopy['tokens'],
            ),
        ];

        $alias = $state;
        $before = count($state->trace);
        $alias->addTrace('VALUE_SEMANTICS', 'object-handle mutation');
        $sameId = spl_object_id($alias) === spl_object_id($state);
        $rows[] = [
            'label' => 'object handle',
            'detail' => sprintf(
                '$alias = $state copies the handle. spl_object_id equal=%s. traces %d → %d on both names. Not a C pointer.',
                $sameId ? 'true' : 'false',
                $before,
                count($state->trace),
            ),
        ];

        $n = 1;
        $ref = &$n;
        $ref = 2;
        $ref++;
        $rows[] = [
            'label' => 'PHP reference (&)',
            'detail' => sprintf(
                '& makes $n and $ref share a zend_reference. $ref++ increments the integer, not an address. $n=%d. IS_REFERENCE, not a C pointer.',
                $n,
            ),
        ];

        $rows[] = [
            'label' => 'Flow Ip',
            'detail' => 'Each Flow job receives $ip->data and returns a value wrapped in a new Ip. Returning this BenchmarkState reuses the object handle. The original Ip object is left unchanged.',
        ];

        return $rows;
    }
}
