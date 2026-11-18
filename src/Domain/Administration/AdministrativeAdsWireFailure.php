<?php
declare(strict_types=1);

namespace Imagine\Performance\Administration;

use RuntimeException;

/** Only stable classifications; no provider body, cursor, headers or previous error. */
final class AdministrativeAdsWireFailure extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $httpStatus = null
    ) {
        parent::__construct('Ads read failed: '.$reason, $httpStatus ?? 0);
    }
}
