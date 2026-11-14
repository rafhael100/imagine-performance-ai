<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use InvalidArgumentException;
use RuntimeException;

/**
 * CI-only adapter: exact numeric ChatGPT Ads mock -> synthetic PG18 V6 report.
 * This is NOT actual provider evidence, and must never be used to authorize
 * invoices, credits or payments. The PG18 close MUST recheck Portal HMAC V6.
 */
final class ChatgptAdsSyntheticWireToV6FinanceLab
{
    /**
     * @param array<string,mixed> $wire normalized synthetic report
     * @param array<string,mixed> $signedPortalQuote V6 candidate, verified by SQL close
     * @return array<string,mixed>
     */
    public static function prepare(
        array $wire,
        array $signedPortalQuote,
        string $expectedTenant,
        string $expectedAgreement,
        string $expectedPeriod,
        string $expectedAccountId,
        string $expectedTimeZone
    ):array {
        if(getenv('APP_ENV')!=='testing' ||
           getenv('PERF_CHATGPT_EXACT_WIRE_MONEY_LAB')!=='1' ||
           getenv('PERF_CHATGPT_WIRE_V6_FINANCE_E2E_LAB')!=='1') {
            throw new RuntimeException('Numeric Ads-to-PG18 synthetic bridge disabled.');
        }
        foreach([
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'account_id'=>$expectedAccountId,
            'currency'=>'BRL',
            'time_zone'=>$expectedTimeZone
        ] as $field=>$value){
            if(!is_string($wire[$field]??null) ||
               !hash_equals($value,$wire[$field])) {
                throw new InvalidArgumentException('ChatGPT account-to-contract scope mismatch.');
            }
        }
        if(($signedPortalQuote['tenant_id']??null)!==$expectedTenant ||
           ($signedPortalQuote['agreement_id']??null)!==$expectedAgreement ||
           ($signedPortalQuote['billing_period']??null)!==$expectedPeriod ||
           ($signedPortalQuote['contract_version']??null)!==
             'performance-trichannel-v6-agreement-candidate' ||
           !is_array($signedPortalQuote['quote']['media_by_channel_cents']??null) ||
           array_keys($signedPortalQuote['quote']['media_by_channel_cents'])!==
             ['chatgpt_ads'] ||
           ($signedPortalQuote['quote']['real_charge_enabled']??null)!==false ||
           ($signedPortalQuote['quote']['management_fee_basis_points']??null)!==2000) {
            throw new InvalidArgumentException('Only a matching, ChatGPT-only signed V6 test contract may stage.');
        }
        foreach(['network_enabled','api_key_verified_by_provider',
                 'portal_iam_authenticated','account_ownership_verified',
                 'provider_spend_verified','finance_approved','billable',
                 'asaas_write_enabled','ads_write_enabled'] as $flag){
            if(($wire[$flag]??null)!==false){
                throw new InvalidArgumentException('Synthetic wire stage must not claim operational authorization.');
            }
        }
        $record=$wire['synthetic_account_report']??null;
        if(!is_array($record)){
            throw new InvalidArgumentException('Synthetic source record missing.');
        }
        $keys=array_keys($record);
        sort($keys,SORT_STRING);
        $expected=[
            'tenant_id','channel','account_id','report_id','period',
            'time_zone','currency','spend_cents','source'
        ];
        sort($expected,SORT_STRING);
        if($keys!==$expected ||
           $record['tenant_id']!==$expectedTenant ||
           $record['account_id']!==$expectedAccountId ||
           $record['channel']!=='chatgpt_ads' ||
           $record['period']!==$expectedPeriod ||
           $record['time_zone']!==$expectedTimeZone ||
           $record['currency']!=='BRL' ||
           $record['source']!=='synthetic_readonly_report_fixture' ||
           !is_int($record['spend_cents']) ||
           $record['spend_cents']<0 || $record['spend_cents']>100000000 ||
           ($wire['state']??null)!=='synthetic_chatgpt_ads_numeric_wire_money_only' ||
           ($wire['numeric_money_parsed_without_binary_float']??null)!==true ||
           !is_int($wire['reported_day_count']??null) ||
           $wire['reported_day_count']<1 ||
           $wire['reported_day_count']>35 ||
           !is_string($wire['source_wire_json_sha256']??null) ||
           !preg_match('/\A[a-f0-9]{64}\z/D',$wire['source_wire_json_sha256'])) {
            throw new InvalidArgumentException('Missing account report provenance or explicit daily mock.');
        }

        // The PG18 lab intentionally accepts 'synthetic_fixture' only. This
        // translation must never be confused with an authenticated statement:
        // report ID includes the RAW mock JSON fingerprint, and all trust flags
        // remain false. The original raw payload is NOT persisted here.
        $synthetic=[
            'channel'=>'chatgpt_ads',
            'period'=>$expectedPeriod,
            'report_id'=>'wirefixture_'.substr($wire['source_wire_json_sha256'],0,48),
            'source'=>'synthetic_fixture',
            'spend_cents'=>$record['spend_cents']
        ];
        return [
            'tenant_id'=>$expectedTenant,
            'agreement_id'=>$expectedAgreement,
            'period'=>$expectedPeriod,
            'account_id'=>$expectedAccountId,
            'mock_source_sha256'=>$wire['source_wire_json_sha256'],
            'reports'=>[$synthetic],
            'state'=>'testing_only_numeric_api_shaped_report_for_v6_pg18',
            'hmac_reverified_by_finance_close'=>false,
            'provider_account_authenticated'=>false,
            'provider_spend_verified'=>false,
            'finance_approved'=>false,
            'billable'=>false,
            'asaas_write_enabled'=>false,
            'ads_write_enabled'=>false
        ];
    }
}
