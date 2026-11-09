<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Domain/Performance/SyntheticPortalEventContract.php';
require_once dirname(__DIR__).'/src/Domain/Performance/SyntheticCampaignRegistry.php';
require_once dirname(__DIR__).'/src/Domain/Performance/SyntheticApprovalsAndReports.php';
require_once dirname(__DIR__).'/src/Domain/Performance/SyntheticPortalDashboardFactory.php';

use Imagine\Performance\Domain\SyntheticPortalDashboardFactory;

// Read-only CLI exporter: no DB, HTTP, secret, filesystem write or external action.
echo json_encode(
    SyntheticPortalDashboardFactory::build(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), "\n";
