<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

require_once __DIR__.'/F160SyntheticSqliteAuditReader.php';

/**
 * F161 synthetic-only evidence CSV; never accepts caller-supplied audit
 * booleans. It must re-read the SQLite state through the F160 validator.
 */
final class F161SyntheticAuditCsvExporter
{
    public function __construct(private F160SyntheticSqliteAuditReader $reader){}

    public function export(string $expectedTenant):?string
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F160_AUDIT_LAB')!=='1'
            ||getenv('PERF_F161_AUDIT_CSV_LAB')!=='1'
            ||!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$expectedTenant)) {
            return null;
        }
        $data=$this->reader->inspect($expectedTenant);
        if(!is_array($data)
            ||($data['schema_version']??null)!=='perf.review-audit.sqlite-lab.v1'
            ||($data['source']??null)!=='sqlite_runner_fixture_only'
            ||($data['tenant_ref']??null)!==$expectedTenant
            ||($data['status']??null)!=='consistent_mock_only'
            ||($data['can_export']??null)!==true
            ||($data['audit_verified_for_production']??null)!==false
            ||($data['read_only']??null)!==true
            ||($data['contracted']??null)!==false
            ||($data['human_approval_verified']??null)!==false
            ||($data['iam_verified']??null)!==false
            ||($data['dispatch_enabled']??null)!==false
            ||($data['billing_enabled']??null)!==false
            ||($data['ready_for_production']??null)!==false
            ||!is_string($data['digest']??null)
            ||!preg_match('/^[a-f0-9]{64}$/D',$data['digest'])
            ||!is_int($data['event_count']??null)
            ||$data['event_count']<0
            ||$data['event_count']>2000
            ||!is_array($data['events']??null)
            ||count($data['events'])!==$data['event_count']) {
            return null;
        }
        $fd=fopen('php://temp','w+');
        if($fd===false)return null;
        try{
            fwrite($fd,"\xEF\xBB\xBF");
            $write=static fn(array $row):int|false=>fputcsv($fd,$row,';','"','');
            if($write([
                'ambiente','empresa_ficticia','evento_sequencial',
                'revisao_ficticia','transicao_ficticia','versao',
                'revisor_ficticio','aprovacao_humana','execucao',
                'faturamento','digest_local_nao_autenticado',
            ])===false)return null;
            $lastEvent=0;
            foreach($data['events'] as $event){
                if(!is_array($event)
                    ||!is_int($event['event_id']??null)
                    ||$event['event_id']<=$lastEvent
                    ||!is_string($event['review_ref']??null)
                    ||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$event['review_ref'])
                    ||!in_array($event['transition']??null,[
                        'submitted_demo','changes_requested_demo',
                        'approved_mock_only','rejected_mock_only','resubmitted_demo',
                    ],true)
                    ||!is_int($event['version']??null)
                    ||$event['version']<1
                    ||!is_string($event['reviewer_fixture']??null)
                    ||!preg_match('/^reviewer-demo-[a-z0-9-]{1,60}$/D',
                        $event['reviewer_fixture'])
                    ||($event['action_authorized']??null)!==false)return null;
                $lastEvent=$event['event_id'];
                if($write([
                    'SOMENTE DEMONSTRACAO',
                    $expectedTenant,
                    (string)$event['event_id'],
                    $event['review_ref'],
                    $event['transition'],
                    (string)$event['version'],
                    $event['reviewer_fixture'],
                    'NAO E APROVACAO REAL',
                    'SEM ANUNCIOS',
                    'SEM COBRANCA',
                    $data['digest'],
                ])===false)return null;
            }
            rewind($fd);
            $csv=stream_get_contents($fd);
            return is_string($csv)?$csv:null;
        }finally{
            fclose($fd);
        }
    }
}
