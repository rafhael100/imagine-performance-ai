<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Offline HTML-only, read-only financial review sheet for synthetic fixtures.
 * No JavaScript, remote resources, payment/gateway controls or action buttons.
 */
final class MonthlySettlementReviewHtml
{
    /** @param array<string,mixed> $candidate */
    public static function render(array $candidate): string
    {
        if (($candidate['state'] ?? null) !== 'synthetic_only_not_authorized_for_financial_execution') {
            throw new InvalidArgumentException('Only synthetic review candidates are supported.');
        }
        foreach (['tenant_authenticated','portal_quote_authenticated','provider_spend_verified','finance_approved','gateway_write_enabled'] as $flag) {
            if (!array_key_exists($flag, $candidate) || $candidate[$flag] !== false) {
                throw new InvalidArgumentException('Untrusted source or financial action flag.');
            }
        }
        $review = $candidate['review_row'] ?? null;
        $rows = $candidate['spend_rows'] ?? null;
        if (!is_array($review) || !is_array($rows) || $rows === [] || count($rows) > 200) {
            throw new InvalidArgumentException('Invalid review data.');
        }
        $month = $review['reference_month'] ?? null;
        if (!is_string($month) || !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])-01\z/D', $month)) {
            throw new InvalidArgumentException('Invalid review month.');
        }
        $advance = $review['advance_fee_cents'] ?? null;
        $earned = $review['earned_fee_cents'] ?? null;
        $delta = $review['adjustment_cents'] ?? null;
        if (!is_int($advance) || !is_int($earned) || !is_int($delta) ||
            $advance < 0 || $advance > 40000000 ||
            $earned < 0 || $earned > 40000000 ||
            $delta !== $earned - $advance) {
            throw new InvalidArgumentException('Review arithmetic invalid.');
        }
        $totals = [];
        $sum = 0;
        foreach ($rows as $row) {
            if (!is_array($row) ||
                !isset($row['reference_month'], $row['channel'], $row['spend_cents']) ||
                $row['reference_month'] !== $month ||
                !is_string($row['channel']) ||
                !in_array($row['channel'], ['google_ads','meta_ads'], true) ||
                !is_int($row['spend_cents']) ||
                $row['spend_cents'] < 0 || $row['spend_cents'] > 100000000) {
                throw new InvalidArgumentException('Malformed synthetic spend row.');
            }
            $channel = $row['channel'];
            $totals[$channel] = ($totals[$channel] ?? 0) + $row['spend_cents'];
            if ($totals[$channel] > 100000000) {
                throw new InvalidArgumentException('Channel cap exceeded.');
            }
            $sum += $row['spend_cents'];
        }
        if (intdiv($sum * 20 + 50, 100) !== $earned) {
            throw new InvalidArgumentException('Earned fee differs from 20% of synthetic spending.');
        }
        ksort($totals, SORT_STRING);
        $table = '';
        foreach ($totals as $channel => $cents) {
            $name = $channel === 'google_ads' ? 'Google Ads' : 'Meta Ads';
            $table .= '<tr><td>' . self::escape($name) . '</td><td class="money">' .
                self::money($cents) . '</td></tr>';
        }
        $label = $delta > 0 ? 'Diferença positiva a conferir' : ($delta < 0 ? 'Diferença negativa a conferir' : 'Sem diferença apurada');
        $when = self::escape(substr($month, 0, 7));
        return '<!doctype html>' . "\n" .
            '<html lang="pt-BR"><head><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width,initial-scale=1">' .
            '<meta name="robots" content="noindex,nofollow,noarchive">' .
            '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; img-src &#39;none&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;">' .
            '<title>Imagine Performance AI — Conferência financeira</title>' .
            '<style>:root{color-scheme:light;font-family:system-ui,-apple-system,Segoe UI,sans-serif}' .
            'body{margin:0;background:#f8fafc;color:#182334;padding:26px}' .
            '.shell{max-width:920px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}' .
            '.brand{font-weight:800;font-size:22px;letter-spacing:-.04em}.brand span{color:#F9941D}' .
            '.tag{font-size:12px;font-weight:700;letter-spacing:.07em;color:#925b00;background:#fff1db;border:1px solid #f5d49e;padding:10px 12px;border-radius:999px}' .
            'h1{font-size:clamp(24px,4vw,36px);margin:26px 0 6px;letter-spacing:-.04em}p{line-height:1.55;color:#4e5b6e}' .
            '.sub{margin:0 0 26px}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(195px,1fr));gap:14px}' .
            '.card,.panel{background:white;border:1px solid #e6eaf0;border-radius:16px;padding:20px}' .
            '.k{font-size:12px;color:#64748b;font-weight:650;text-transform:uppercase;letter-spacing:.05em}' .
            '.v{font-size:26px;letter-spacing:-.04em;font-weight:780;margin-top:12px}' .
            '.accent{border-top:4px solid #F9941D}.panel{margin-top:18px}h2{font-size:18px;margin:0 0 16px}' .
            'table{border-collapse:collapse;width:100%;font-size:14px}td{border-top:1px solid #edf1f5;padding:13px 3px}' .
            '.money{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}' .
            '.foot{border-left:4px solid #F9941D;padding:12px 16px;background:#fff9f0;color:#654b24;margin:20px 0;border-radius:0 10px 10px 0}' .
            '@media(max-width:600px){body{padding:16px}.card,.panel{padding:16px}.v{font-size:24px}}' .
            '</style></head><body><main class="shell">' .
            '<header class="top"><div class="brand">imagine<span>.</span> Performance AI</div>' .
            '<div class="tag">DEMONSTRAÇÃO NÃO HOMOLOGADA</div></header>' .
            '<h1>Conferência mensal de honorários</h1>' .
            '<p class="sub">Competência ' . $when . ' · valores sintéticos · moeda BRL · apenas para revisão interna</p>' .
            '<section class="metrics"><article class="card"><div class="k">Honorários antecipados</div>' .
            '<div class="v">' . self::money($advance) . '</div></article>' .
            '<article class="card"><div class="k">Honorários calculados sobre gasto informado</div>' .
            '<div class="v">' . self::money($earned) . '</div></article>' .
            '<article class="card accent"><div class="k">' . self::escape($label) . '</div>' .
            '<div class="v">' . self::signedMoney($delta) . '</div></article></section>' .
            '<section class="panel"><h2>Mídia informada por plataforma</h2><table><tbody>' .
            $table . '<tr><td><strong>Total de mídia (paga fora da Imagine)</strong></td><td class="money">' .
            self::money($sum) . '</td></tr></tbody></table></section>' .
            '<aside class="foot"><strong>Conferência necessária.</strong> Valores e origens não autenticados. ' .
            'Nenhum acerto, estorno, crédito, cobrança Asaas ou comando de anúncios foi autorizado.</aside>' .
            '</main></body></html>';
    }

    private static function money(int $cents): string
    {
        $reais = (string)intdiv($cents, 100);
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $reais);
        return 'R$ ' . $grouped . ',' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function signedMoney(int $cents): string
    {
        return ($cents < 0 ? '−' : ($cents > 0 ? '+' : '')) . self::money(abs($cents));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
