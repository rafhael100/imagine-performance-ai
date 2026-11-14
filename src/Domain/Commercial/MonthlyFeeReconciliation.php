<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;

/**
 * Simulador puro de honorários. NÃO emite cobrança, crédito ou comandos de mídia.
 * Entradas em centavos BRL; números inteiros, sem floats.
 */
final class MonthlyFeeReconciliation
{
    private const MIN_MEDIA_PER_CHANNEL_CENTS = 70000;
    // Mesmo teto por canal da cotação do Portal (ApprovedOfferCatalog::quote).
    // Teto técnico de homologação, não é uma autorização para gasto.
    private const MAX_MEDIA_CENTS = 100000000;

    /**
     * @param array<string, array{planned_cents:int, actual_cents:int}> $channels
     * @return array{currency:string, period:string, simulated:bool, planned_media_cents:int, actual_media_cents:int, advance_fee_cents:int, earned_fee_cents:int, adjustment_cents:int, settlement_status:string, channels:array<string, array<string, int>>}
     */
    public static function calculate(string $period, array $channels): array
    {
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/D', $period)) {
            throw new InvalidArgumentException('Invalid YYYY-MM period.');
        }
        if ($channels === [] || count($channels) > 2 || array_diff(array_keys($channels), ['google_ads', 'meta_ads']) !== []) {
            throw new InvalidArgumentException('Only one or two unique Google/Meta channels are supported.');
        }

        $plannedTotal = 0;
        $actualTotal = 0;
        $rows = [];
        ksort($channels, SORT_STRING);
        foreach ($channels as $channel => $input) {
            if (!is_array($input) || array_keys($input) !== ['planned_cents', 'actual_cents']) {
                throw new InvalidArgumentException('Exact planned_cents and actual_cents fields required.');
            }
            $planned = $input['planned_cents'];
            $actual = $input['actual_cents'];
            if (!is_int($planned) || !is_int($actual) ||
                $planned < self::MIN_MEDIA_PER_CHANNEL_CENTS || $planned > self::MAX_MEDIA_CENTS ||
                $actual < 0 || $actual > self::MAX_MEDIA_CENTS) {
                throw new InvalidArgumentException('Invalid channel media amount in integer cents.');
            }
            $plannedTotal += $planned;
            $actualTotal += $actual;
            $rows[$channel] = [
                'planned_cents' => $planned,
                'actual_cents' => $actual,
                'advance_fee_cents' => self::fee($planned),
                'earned_fee_cents' => self::fee($actual),
            ];
        }

        // Apuração sobre a soma, evitando divergência de arredondamento entre canais.
        $advance = self::fee($plannedTotal);
        $earned = self::fee($actualTotal);
        $difference = $earned - $advance;

        return [
            'currency' => 'BRL',
            'period' => $period,
            'simulated' => true,
            'planned_media_cents' => $plannedTotal,
            'actual_media_cents' => $actualTotal,
            'advance_fee_cents' => $advance,
            'earned_fee_cents' => $earned,
            'adjustment_cents' => $difference,
            'settlement_status' => 'requires_finance_review_no_auto_charge_or_credit',
            'channels' => $rows,
        ];
    }

    private static function fee(int $cents): int
    {
        // 20%, arredondamento half-up em centavos, sem ponto flutuante.
        return intdiv($cents * 20 + 50, 100);
    }
}
