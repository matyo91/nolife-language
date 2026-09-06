<?php

declare(strict_types=1);

namespace App\Flow;

use Flow\FlowInterface;
use Flow\Ip;
use stdClass;

/**
 * Captures the final job payload. Flow wraps each stage in a new Ip;
 * the original Ip is unchanged.
 */
final class FlowCollector
{
    /**
     * @template T
     *
     * @param FlowInterface<mixed> $flow
     * @param Ip<mixed>            $ip
     *
     * @return T
     */
    public static function run(FlowInterface $flow, Ip $ip): mixed
    {
        $box = new stdClass();
        $box->value = null;

        $flow->fn(static function (mixed $data) use ($box): mixed {
            $box->value = $data;

            return $data;
        });

        ($flow)($ip);
        $flow->await();

        return $box->value;
    }
}
