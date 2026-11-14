<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial\Lab;

use Imagine\Performance\Commercial\PortalTriChannelV5CandidateGuard;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * READ ONLY, CI ONLY — renders an unbillable Portal HMAC V5 close draft.
 * Does not require or persist personally identifiable data. No server routes,
 * passwords, adverts, invoices, commands, buttons or network access.
 */
final class PgTriChannelV5ReviewHtml
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $signed
     * @param array<string,int> $floors trusted, pinned Portal owner-approved decision
     * @param list<array<string,mixed>> $records SYNTHETIC original rows, NEVER a caller-supplied hash
     * @param callable(string,string):?string $resolveKey
     */
    public function render(
        array $signed,
        string $expectedTenant,
        string $period,
        string $catalogSha256,
        array $floors,
        array $records,
        int $now,
        callable $resolveKey
    ): string {
        if (getenv('APP_ENV') !== 'testing' ||
            getenv('PERF_PORTAL_TRICHANNEL_V5_LAB') !== '1' ||
            getenv('PERF_PG_TRICHANNEL_V5_HTML_LAB') !== '1' ||
            !preg_match('/\Atenant_[A-Za-z0-9_-]{8,64}\z/D', $expectedTenant) ||
            !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D', $period) ||
            $this->db->inTransaction() ||
            $this->db->query('SELECT current_user')->fetchColumn() !== 'perf_fin_lab_app') {
            throw new InvalidArgumentException('V5 financial HTML laboratory disabled or invalid.');
        }
        // Rebuild the precise evidence bytes from original synthetic records.
        // A client-supplied digest + a changed account/channel split is NEVER sufficient.
        $planned = $signed['quote']['media_by_channel_cents'] ?? null;
        if (!is_array($planned) || $planned === [] ||
            count($records) < 1 || count($records) > 200) {
            throw new InvalidArgumentException('Original synthetic evidence rows required.');
        }
        $allowed = ['chatgpt_ads','google_ads','meta_ads'];
        $seen = [];
        $unverifiedSpend = [];
        $canonical = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Invalid V5 report record.');
            }
            $fields = array_keys($record);
            sort($fields, SORT_STRING);
            if ($fields !== ['channel','period','report_id','source','spend_cents']) {
                throw new InvalidArgumentException('Unknown V5 evidence field.');
            }
            $channel = $record['channel'];
            $id = $record['report_id'];
            $cost = $record['spend_cents'];
            if (!is_string($channel) || !in_array($channel, $allowed, true) ||
                !array_key_exists($channel, $planned) ||
                $record['period'] !== $period ||
                $record['source'] !== 'synthetic_fixture' ||
                !is_string($id) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $id) ||
                !is_int($cost) || $cost < 0 || $cost > 100000000) {
                throw new InvalidArgumentException('Invalid V5 expense receipt.');
            }
            $key = $channel . "\0" . $id;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicated V5 receipt.');
            }
            $seen[$key] = true;
            $current = $unverifiedSpend[$channel] ?? 0;
            if ($current > 100000000 - $cost) {
                throw new InvalidArgumentException('V5 channel total exceeds cap.');
            }
            $unverifiedSpend[$channel] = $current + $cost;
            $canonical[] = [
                'channel'=>$channel,'period'=>$period,
                'report_id'=>$id,'source'=>'synthetic_fixture','spend_cents'=>$cost
            ];
        }
        $expected = array_keys($planned);
        $provided = array_keys($unverifiedSpend);
        sort($expected, SORT_STRING);
        sort($provided, SORT_STRING);
        if ($expected !== $provided) {
            throw new InvalidArgumentException('Missing channel evidence; explicit zero required.');
        }
        ksort($unverifiedSpend, SORT_STRING);
        usort($canonical, static fn(array $a,array $b):int =>
            [$a['channel'],$a['report_id']] <=> [$b['channel'],$b['report_id']]);
        $spendFingerprint = hash('sha256', json_encode(
            $canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ));
        try {
            $this->db->beginTransaction();
            $this->db->prepare("SELECT set_config('app.tenant_id',:tenant,true)")
                ->execute(['tenant'=>$expectedTenant]);
            // Re-check full HMAC and price without re-claiming an already persisted
            // nonce. A nonce is "known" only when read under the same lab tenant.
            $knownNonce = function (string $tenant,string $key,string $nonce,int $expires):bool {
                $q=$this->db->prepare(
                    'SELECT expires_at FROM perf_fin_lab.signed_quote_nonces
                     WHERE tenant_id=:tenant AND key_id=:key AND nonce=:nonce'
                );
                $q->execute(['tenant'=>$tenant,'key'=>$key,'nonce'=>$nonce]);
                $stored=$q->fetchColumn();
                return $stored!==false && (int)$stored===$expires;
            };
            $verified=PortalTriChannelV5CandidateGuard::verify(
                $signed,$expectedTenant,$period,$catalogSha256,
                $floors,$unverifiedSpend,$now,$resolveKey,$knownNonce
            );
            if (($verified['quote_claim_hmac_verified'] ?? null)!==true ||
                ($verified['billable'] ?? null)!==false ||
                ($verified['finance_approved'] ?? null)!==false ||
                ($verified['provider_spend_verified'] ?? null)!==false ||
                ($verified['asaas_write_enabled'] ?? null)!==false) {
                throw new InvalidArgumentException('V5 preview requires unbillable candidate.');
            }
            $q=$this->db->prepare(
                'SELECT quote_claim_sha256,spend_claim_sha256,planned_media_cents,
                    unverified_actual_media_cents,advance_fee_cents,
                    unverified_earned_fee_cents,adjustment_cents,
                    billable,finance_approved,source_verified,status
                 FROM perf_fin_lab.reconciliation_drafts
                 WHERE tenant_id=:tenant AND period=:period AND quote_claim_sha256=:quote'
            );
            $q->execute([
                'tenant'=>$expectedTenant,'period'=>$period,
                'quote'=>$verified['quote_claim_sha256'],
            ]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) ||
                $row['quote_claim_sha256']!==$verified['quote_claim_sha256'] ||
                $row['spend_claim_sha256']!==$spendFingerprint ||
                $row['status']!=='lab_draft_unverified_spend' ||
                !self::dbFalse($row['billable']) ||
                !self::dbFalse($row['finance_approved']) ||
                !self::dbFalse($row['source_verified'])) {
                throw new InvalidArgumentException('Untrusted V5 finance record or evidence fingerprint.');
            }
            foreach ([
                'planned_media_cents'=>'planned_media_cents',
                'unverified_actual_media_cents'=>'unverified_actual_media_cents',
                'advance_fee_cents'=>'estimated_advance_fee_cents',
                'unverified_earned_fee_cents'=>'unverified_earned_fee_cents',
                'adjustment_cents'=>'review_only_adjustment_cents',
            ] as $column=>$verifiedKey) {
                if ((int)$row[$column] !== $verified[$verifiedKey]) {
                    throw new InvalidArgumentException('Stored immutable values differ from HMAC preview.');
                }
            }
            $this->db->rollBack(); // SELECT only, clears transaction-local lab tenant

            $labels=[
                'chatgpt_ads'=>'ChatGPT Ads',
                'google_ads'=>'Google Ads',
                'meta_ads'=>'Meta Ads',
            ];
            $planned=$signed['quote']['media_by_channel_cents'];
            ksort($planned,SORT_STRING);
            $rows='';
            foreach ($planned as $channel=>$plannedCents) {
                $rows.='<tr><td>'.self::escape($labels[$channel]).
                    '</td><td>'.self::brl($plannedCents).
                    '</td><td>'.self::brl($unverifiedSpend[$channel]).
                    '</td></tr>';
            }
            $delta=$verified['review_only_adjustment_cents'];
            $deltaLabel=($delta>0?'+':($delta<0?'−':'')) . self::brl(abs($delta));
            return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'.
                '<meta name="viewport" content="width=device-width,initial-scale=1">'.
                '<meta name="robots" content="noindex,nofollow">'.
                '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; base-uri &#39;none&#39;">'.
                '<title>Imagine Performance AI — Revisão financeira V5</title>'.
                '<style>'.
                ':root{color-scheme:light;--a:#F9941D;--txt:#253547;--mut:#66788B;--bd:#E5EBF1}'.
                '*{box-sizing:border-box}body{margin:0;background:#F6F8FB;color:var(--txt);font:16px/1.5 system-ui,Arial,sans-serif}'.
                'main{max-width:960px;margin:38px auto;padding:0 18px}'.
                'article{background:#fff;border:1px solid var(--bd);border-radius:20px;padding:30px;box-shadow:0 10px 30px #14213d0a}'.
                'header{display:flex;align-items:center;gap:12px;margin-bottom:24px}.logo{background:var(--a);color:white;border-radius:11px;padding:7px 13px;font-weight:900}'.
                'h1{font-size:27px;letter-spacing:-.7px;margin:0}.mut{color:var(--mut);font-size:14px}'.
                '.alert{background:#FFF4E6;border:1px solid #F4CA8E;border-radius:12px;padding:13px;margin:12px 0 22px;font-weight:700;color:#7C500F}'.
                '.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:23px 0}'.
                '.stat{border:1px solid var(--bd);border-radius:12px;padding:16px}.stat span{display:block;color:var(--mut);font-size:13px}.stat strong{font-size:22px}'.
                'table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid var(--bd)}th{background:#F7F9FC;font-size:13px}'.
                '.foot{margin-top:24px;border-top:1px solid var(--bd);padding-top:20px;font-size:13px;color:var(--mut)}'.
                '@media(max-width:620px){.stats{grid-template-columns:1fr}article{padding:20px}h1{font-size:22px}main{margin:16px auto}}'.
                '</style></head><body><main><article><header><span class="logo">i</span><div><h1>Imagine Performance AI</h1>'.
                '<div class="mut">Revisão de pré-fechamento tricanal — '.$period.'</div></div></header>'.
                '<div class="alert">DEMONSTRAÇÃO NÃO HOMOLOGADA — sem gasto oficial, aprovação ou cobrança.</div>'.
                '<p class="mut">Empresa fictícia: '.self::escape($expectedTenant).
                ' · Contrato candidato V5 assinado (somente laboratório).</p>'.
                '<div class="stats"><div class="stat"><span>Mídia planejada, paga aos canais</span><strong>'.
                self::brl($verified['planned_media_cents']).'</strong></div>'.
                '<div class="stat"><span>Gestão ilustrativa 20% sobre mídia prevista</span><strong>'.
                self::brl($verified['estimated_advance_fee_cents']).'</strong></div>'.
                '<div class="stat"><span>Gestão sobre gasto sintético</span><strong>'.
                self::brl($verified['unverified_earned_fee_cents']).'</strong></div></div>'.
                '<h2>Detalhamento por canal</h2><table><thead><tr><th>Canal</th><th>Mídia prevista</th>'.
                '<th>Mídia sintética (não verificada)</th></tr></thead><tbody>'.$rows.'</tbody></table>'.
                '<p><strong>Ajuste apenas para conferência: '.$deltaLabel.'</strong></p>'.
                '<p class="foot">Mídia paga diretamente aos provedores. Este relatório não é comprovante oficial, fatura, crédito, autorização de mídia ou aceite do cliente. Identidade/IAM e fonte de gasto não verificadas. Não contém scripts, formulários, consultas externas, dados pessoais ou comandos de pagamento.</p>'.
                '</article></main></body></html>';
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Read-only V5 finance review unavailable or evidence differs.');
        }
    }

    private static function dbFalse(mixed $value): bool
    {
        return in_array($value,[false,0,'0','f'],true);
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }

    private static function brl(int $cents): string
    {
        return 'R$ '.number_format($cents/100,2,',','.');
    }
}
