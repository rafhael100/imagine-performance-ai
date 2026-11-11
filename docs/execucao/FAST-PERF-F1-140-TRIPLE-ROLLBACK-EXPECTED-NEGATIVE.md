# FAST-PERF F1-140 — restauração conjunta de três bancos (teste esperado-negativo)

**Base F139 (PR #247); CI com três contêineres PostgreSQL16 descartáveis no mesmo runner. Estado: DRAFT / NO-GO.**

## Experimento de vulnerabilidade

F138 e F139 bloquearam alguns replays após restauração dos dois ledgers desde que a terceira âncora permanecesse mais recente. Porém a terceira âncora não está fora do mesmo domínio de falha. F140 testa o limite explícito:

1. Grava operação A com geração 8 no primário, witness e terceira âncora.
2. Executa `pg_dump --data-only` de **todos os três** bancos no epoch 8. Os arquivos são sintéticos no runner.
3. Eleva o piso da âncora para 9, o piso da witness para 9 e a geração primária para 9. Registra B, confirma em cada ledger e demonstra rejeição de uma segunda tentativa enquanto os ledgers estão íntegros.
4. Restaura os dados de **cada um dos três bancos** aos snapshots de geração 8 com `TRUNCATE + psql`. A âncora perde o aumento para 9 e os recibos B desaparecem.
5. Repete B pelo bridge protegido F139: a witness reemite e redime, e a âncora restaurada aprova geração 8. **A duplicação é reproduzida.** O teste é verde apenas se mostrar essa vulnerabilidade, e toda resposta continua `action_authorized=false`.

## Conclusão de segurança

A âncora só ajuda quando o histórico dela sobrevive ao evento que afetou os ledgers. Uma terceira instância no mesmo runner de CI **não é um antirollback verdadeiro**. A função que permite elevar a geração mas não reduzi-la é insuficiente: restauração de backup apaga toda a história local. F139 reduz o risco de atualização concorrente do piso durante transação normal, mas não protege evento de restauração ou desastre conjunto.

O desenho de produção ainda exige autoridade externa com isolamento de administração/credenciais, propriedade de retenção imutável (ou equivalente verificável), identificação do workload, controles de consenso/reconciliação e despacho fail-closed. NENHUMA dessas exigências foi homologada aqui. Também continuam pendentes AppSec/DBA independente, Portal IAM/MFA/S2S, KMS/HSM, consentimentos Google/Meta, orçamento de mídia e gates P0/HT-R01/SEC-R03/T3.

**Não usar como gate real de autorização de anúncios nem liberar produção.** Não realizar merge na main, deploy VPS, cobranças, veiculação, acesso a dados de clientes ou despesas.
