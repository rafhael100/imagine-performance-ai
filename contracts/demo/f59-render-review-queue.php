<?php
declare(strict_types=1);

/**
 * F1-59 offline renderer. Uses only the F1-58 fake queue DTO, never a live
 * reviewer, Portal session, database or provider. Emits HTML to stdout.
 */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
  ||getenv('PERF_F52_SCOPE_LAB')!=='1'
  ||getenv('PERF_F57_REVIEW_LAB')!=='1'
  ||getenv('PERF_F58_REVIEW_QUEUE_LAB')!=='1'
  ||getenv('PERF_F59_UI_LAB')!=='1'){
    throw new RuntimeException('F59 offline review UI requires explicit CLI lab gates');
}
ob_start();
require __DIR__.'/f58-generate-review-queue.php';
$raw=ob_get_clean();
if(!is_string($raw))throw new RuntimeException('F59 fake queue not generated');
$dto=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if(!is_array($dto)
  ||$dto['schema_version']!=='perf.portal.review-queue.synthetic.v1'
  ||$dto['source']!=='in_memory_review_fixture_only'
  ||$dto['tenant_ref']!=='perf-demo-aurora'
  ||$dto['read_only']!==true
  ||$dto['contracted']!==false
  ||$dto['human_identity_verified']!==false
  ||$dto['authorization_eligible']!==false
  ||$dto['ready_for_production']!==false
  ||$dto['dispatch_enabled']!==false
  ||$dto['billing_enabled']!==false
  ||$dto['scope_changes_permitted']!==false){
    throw new RuntimeException('F59 unsafe review DTO refused');
}
$template=file_get_contents(__DIR__.'/performance-review-queue-v1.html');
if(!is_string($template)||strlen($template)>80000){
    throw new RuntimeException('Missing F59 offline HTML template');
}
$pattern='~(<script id="review-data" type="application/json">)(.*?)(</script>)~s';
if(preg_match_all($pattern,$template)!==1){
    throw new RuntimeException('F59 requires exactly one static JSON template slot');
}
$json=json_encode($dto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    |JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
$html=preg_replace_callback($pattern,
    static fn(array $m):string=>$m[1]."\n".$json."\n".$m[3],
    $template,1,$replaced
);
if(!is_string($html)||$replaced!==1){
    throw new RuntimeException('F59 could not render offline mock review UI');
}
echo $html;
