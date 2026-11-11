<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F150ReleaseEvidenceGate.php';

use Imagine\Performance\Domain\F150ReleaseEvidenceGate;

if (PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
    ||getenv('PERF_F150_RELEASE_LAB')!=='1') {
    fwrite(STDERR,"F150 release evidence available for synthetic CLI only\n");
    exit(2);
}
$source=dirname(__DIR__,2).'/contracts/release/f150-lab-gate-evidence.json';
$payload=json_decode((string)file_get_contents($source),true,32,JSON_THROW_ON_ERROR);
$report=(new F150ReleaseEvidenceGate())->evaluate($payload);
if($report['release']!=='NO_GO'||$report['can_enable_ads']!==false){
    throw new RuntimeException('F150 cannot grant a release');
}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
