/*
 * Browser-only demonstration of the THREE-channel PROPOSAL calculator.
 * No network, PII capture, cookies, local storage, CRM, ad writes or billing.
 * Commercial values are NOT approved in Imagine Portal.
 */
export const PROPOSED_MINIMA = Object.freeze({
  chatgpt_ads: 120000,
  google_ads: 100000,
  meta_ads: 70000
});
export const REFERENCE_RECOMMENDED = Object.freeze({
  chatgpt_ads: 180000,
  google_ads: 150000,
  meta_ads: 100000
});
const MAX_LEADS = 3000;
const MAX_CPL = 10000000;
const MAX_MEDIA = 100000000;

export function parseBrlCents(value) {
  if (typeof value !== 'string') throw new Error('CPL deve ser um valor em reais.');
  const str = value.trim().replace(/^R\$\s*/, '').trim();
  const match = /^(0|[1-9][0-9]{0,5})(?:[,.]([0-9]{1,2}))?$/.exec(str);
  if (!match) throw new Error('Digite um CPL estimado, por exemplo, 80,00.');
  const cents = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
  if (!Number.isSafeInteger(cents) || cents <= 0 || cents > MAX_CPL) {
    throw new Error('CPL fora do limite técnico da simulação.');
  }
  return cents;
}

export function calculateScenario(period, desiredLeads, costsByChannel) {
  const year = Number((period || '').slice(0, 4));
  if (typeof period !== 'string' || !/^[0-9]{4}-(0[1-9]|1[0-2])$/.test(period) ||
      year < 2000 || year > 2100 ||
      !Number.isInteger(desiredLeads) || desiredLeads < 1 || desiredLeads > MAX_LEADS ||
      !costsByChannel || typeof costsByChannel !== 'object' ||
      Array.isArray(costsByChannel)) {
    throw new Error('Informe mês válido e meta de 1 a 3.000 leads.');
  }
  const names = Object.keys(costsByChannel).sort();
  if (names.length < 1 || names.length > 3) {
    throw new Error('Selecione ao menos um dos três canais.');
  }
  const base = Math.floor(desiredLeads / names.length);
  const remainder = desiredLeads % names.length;
  let floorTotal = 0, suggestedTotal = 0, capacity = 0;
  const channels = {};
  for (let index = 0; index < names.length; index++) {
    const name = names[index];
    const cpl = costsByChannel[name];
    if (!Object.hasOwn(PROPOSED_MINIMA, name) ||
        !Number.isSafeInteger(cpl) || cpl < 1 || cpl > MAX_CPL) {
      throw new Error('Canal ou custo por lead não permitido na simulação.');
    }
    const allocated = base + (index < remainder ? 1 : 0);
    const targetSpend = allocated * cpl;
    const suggested = Math.max(targetSpend, PROPOSED_MINIMA[name],
      REFERENCE_RECOMMENDED[name]);
    if (!Number.isSafeInteger(suggested) || suggested > MAX_MEDIA) {
      throw new Error('Meta excede o teto técnico desta simulação.');
    }
    floorTotal += PROPOSED_MINIMA[name];
    suggestedTotal += suggested;
    const possible = Math.floor(suggested / cpl);
    capacity += possible;
    channels[name] = {
      desired_leads_allocated: allocated,
      assumed_cpl_cents: cpl,
      proposed_monthly_floor_cents: PROPOSED_MINIMA[name],
      reference_recommended_cents: REFERENCE_RECOMMENDED[name],
      target_based_media_cents: targetSpend,
      suggested_monthly_media_cents: suggested,
      illustrative_capacity_leads_at_assumed_cpl: possible
    };
  }
  const fee = Math.floor((suggestedTotal * 20 + 50) / 100);
  return {
    period, desired_leads: desiredLeads,
    state: 'illustrative_lead_magnet_scenario_not_a_quote',
    allocation_strategy: 'equal_by_selected_channel_alphabetical_remainder',
    channels,
    proposed_monthly_floor_total_cents: floorTotal,
    suggested_monthly_media_total_cents: suggestedTotal,
    illustrative_fee_on_suggested_media_cents: fee,
    illustrative_total_separate_outlay_cents: suggestedTotal + fee,
    illustrative_capacity_leads_at_assumed_cpl: capacity,
    cpl_is_user_assumption_not_provider_estimate: true,
    leads_or_results_guaranteed: false,
    portal_catalog_approved: false,
    crm_lead_saved: false,
    personal_data_collected: false,
    asaas_write_enabled: false,
    ads_write_enabled: false
  };
}

const fmt = cents => 'R$ ' + (cents / 100).toLocaleString('pt-BR', {
  minimumFractionDigits: 2, maximumFractionDigits: 2
});
const labels = {
  chatgpt_ads: 'ChatGPT Ads', google_ads: 'Google Ads', meta_ads: 'Meta Ads'
};

function start() {
  const root = document.getElementById('calc');
  if (!root) return;
  const result = document.getElementById('calc-result');
  const feedback = document.getElementById('calc-feedback');
  const summary = document.getElementById('calc-summary');
  const rows = document.getElementById('calc-rows');
  function render() {
    try {
      const period = document.getElementById('period').value;
      const desiredLeads = Number(document.getElementById('desired-leads').value);
      const inputs = {};
      for (const name of Object.keys(PROPOSED_MINIMA)) {
        if (document.getElementById('use-' + name).checked) {
          inputs[name] = parseBrlCents(document.getElementById('cpl-' + name).value);
        }
      }
      const estimate = calculateScenario(period, desiredLeads, inputs);
      feedback.textContent = '';
      result.hidden = false;
      document.getElementById('total-media').textContent =
        fmt(estimate.suggested_monthly_media_total_cents);
      document.getElementById('total-fee').textContent =
        fmt(estimate.illustrative_fee_on_suggested_media_cents);
      document.getElementById('total-sum').textContent =
        fmt(estimate.illustrative_total_separate_outlay_cents);
      document.getElementById('capacity').textContent =
        String(estimate.illustrative_capacity_leads_at_assumed_cpl);
      rows.replaceChildren();
      const report = ['SIMULAÇÃO — NÃO É ORÇAMENTO APROVADO',
        'Competência: ' + estimate.period, 'Meta desejada: ' + desiredLeads + ' leads',
        'Mídia paga diretamente aos canais: ' + fmt(estimate.suggested_monthly_media_total_cents),
        'Honorários ilustrativos Imagine (20%): ' +
          fmt(estimate.illustrative_fee_on_suggested_media_cents)];
      for (const [name, item] of Object.entries(estimate.channels)) {
        const tr = document.createElement('tr');
        for (const value of [labels[name], String(item.desired_leads_allocated),
              fmt(item.proposed_monthly_floor_cents),
              fmt(item.suggested_monthly_media_cents),
              fmt(item.assumed_cpl_cents)]) {
          const td = document.createElement('td');
          td.textContent = value;
          tr.appendChild(td);
        }
        rows.appendChild(tr);
        report.push(labels[name] + ': mídia ' +
          fmt(item.suggested_monthly_media_cents) + ', CPL assumido ' +
          fmt(item.assumed_cpl_cents));
      }
      report.push('Valores hipotéticos. Leads não garantidos. Nenhuma informação salva no CRM.');
      summary.value = report.join('\n');
    } catch (error) {
      result.hidden = true;
      summary.value = '';
      feedback.textContent = error.message;
    }
  }
  root.addEventListener('input', render);
  root.addEventListener('change', render);
  document.getElementById('copy-summary').addEventListener('click', async () => {
    const status = document.getElementById('copy-status');
    try {
      if (!summary.value || !navigator.clipboard?.writeText) {
        status.textContent = 'Resumo disponível no campo para copiar manualmente.';
        return;
      }
      await navigator.clipboard.writeText(summary.value);
      status.textContent = 'Resumo copiado. Nenhum dado enviado.';
    } catch (_) {
      status.textContent = 'Selecione e copie o resumo manualmente.';
    }
  });
  render();
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
}
