<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/src/Domain/Performance/SyntheticReleaseNoGoAuditor.php';
$root=dirname(__DIR__,3);
try {
    $policy=json_decode(
        (string)file_get_contents($root.'/contracts/lab/ci/performance-release-no-go.json'),
        true,16,JSON_THROW_ON_ERROR
    );
    $bootstrap=file_get_contents($root.'/bootstrap/app.php');
    $audit=(new \Imagine\Performance\Domain\SyntheticReleaseNoGoAuditor())
        ->assess($policy,$bootstrap);
} catch (Throwable) {
    $audit=(new \Imagine\Performance\Domain\SyntheticReleaseNoGoAuditor())
        ->assess(null,null);
}
echo json_encode($audit,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($audit['decision']==='BLOCKED_AS_EXPECTED_NO_GO'?3:4);
