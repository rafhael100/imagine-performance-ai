<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * FAST-PERF F1-76. Compare two independently supplied OFFLINE fake datasets.
 * Neither input is a provider response or a trustworthy authorization source.
 * Not wired to Laravel routes, queues or Ads API.
 */
final class SyntheticOfflineProviderReconciler
{
    private const SCHEMA = 'perf.provider.reconcile.synthetic.v1';
    private const ACTIONS = ['create','pause','resume','swap'];
    private const RECEIPT_STATUSES = ['accepted_mock','rejected_mock'];

    private static function result(string $status, int $intents=0, int $receipts=0, int $pending=0): array
    {
        return [
            'schema_version' => self::SCHEMA,
            'status' => $status,
            'counts' => ['intents'=>$intents, 'receipts'=>$receipts, 'pending'=>$pending],
            'provider_was_contacted' => false,
            'authenticated_provider_evidence' => false,
            'recovery_monotonicity_proven' => false,
            'authorization_eligible' => false,
            'read_preview_eligible' => false,
            'ready_for_production' => false,
            'dispatch_enabled' => false,
            'billing_enabled' => false,
        ];
    }

    public function inspect(mixed $intents, mixed $receipts, string $tenant, int $minimumEpoch): array
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F76_RECONCILIATION_LAB')!=='1') {
            return self::result('lab_disabled');
        }
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D', $tenant)!==1
            || $minimumEpoch<1 || $minimumEpoch>1000000
            || !is_array($intents) || !is_array($receipts)
            || !array_is_list($intents) || !array_is_list($receipts)
            || count($intents)>100 || count($receipts)>100) {
            return self::result('invalid_fixture');
        }

        $known = [];
        foreach ($intents as $intent) {
            if (!is_array($intent)
                || array_keys($intent)!==['intent_id','tenant','operation','request_hash','epoch','approved_mock']
                || !$this->hex($intent['intent_id'],32)
                || $intent['tenant']!==$tenant
                || !in_array($intent['operation'],self::ACTIONS,true)
                || !$this->hex($intent['request_hash'],64)
                || !is_int($intent['epoch']) || $intent['epoch']<$minimumEpoch
                || $intent['epoch']>1000000 || !is_bool($intent['approved_mock'])
                || isset($known[$intent['intent_id']])) {
                return self::result('invalid_fixture');
            }
            $known[$intent['intent_id']] = $intent;
        }
        $seenReceipts = [];
        $seenIntents = [];
        $hasRejection=false;
        foreach ($receipts as $receipt) {
            if (!is_array($receipt)
                || array_keys($receipt)!==['receipt_id','intent_id','tenant','request_hash','epoch','status']
                || !$this->hex($receipt['receipt_id'],32)
                || !$this->hex($receipt['intent_id'],32)
                || $receipt['tenant']!==$tenant
                || !$this->hex($receipt['request_hash'],64)
                || !is_int($receipt['epoch']) || $receipt['epoch']<$minimumEpoch
                || $receipt['epoch']>1000000
                || !in_array($receipt['status'],self::RECEIPT_STATUSES,true)) {
                return self::result('invalid_fixture');
            }
            $id=$receipt['intent_id'];
            if (isset($seenReceipts[$receipt['receipt_id']]) || isset($seenIntents[$id])
                || !isset($known[$id])) {
                return self::result('conflict_detected');
            }
            $seenReceipts[$receipt['receipt_id']]=true;
            $seenIntents[$id]=true;
            $intent=$known[$id];
            if (!hash_equals($intent['request_hash'],$receipt['request_hash'])
                || $receipt['epoch']!==$intent['epoch']
                || ($receipt['status']==='accepted_mock' && $intent['approved_mock']!==true)) {
                return self::result('conflict_detected');
            }
            $hasRejection=$hasRejection||$receipt['status']==='rejected_mock';
        }

        $pending=count($known)-count($seenIntents);
        $status= count($known)===0 ? 'empty_mock_only'
            : ($hasRejection ? 'mock_rejected'
            : ($pending>0 ? 'awaiting_mock_receipts' : 'matched_mock_only'));
        return self::result($status,count($known),count($receipts),$pending);
    }

    private function hex(mixed $value,int $length):bool
    {
        return is_string($value)
            && strlen($value)===$length
            && preg_match('/^[a-f0-9]+$/D',$value)===1;
    }
}
