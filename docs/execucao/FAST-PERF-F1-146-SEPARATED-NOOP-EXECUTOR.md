# FAST-PERF F1-146 — executor sintético em processo separado e grants Ed25519

**Base:** F145, PR #253. **Modo:** exclusivamente CI de laboratório / DRAFT / NO-GO. O único destino implementado é `synthetic-noop`. Não existe SDK, API, acesso de rede, token, conta Google Ads/Meta Ads, orçamento, anúncio ou cobrança.

## O que foi desenvolvido

- **Emissor distinto do executor:** `F146TerminalGrantIssuer` recebe a decisão positiva *sintética* da barreira F145, mas não confia apenas no booleano recebido. Confere o diário assinado F145, o hash/contador/piso do high-watermark e o **vínculo exato entre operation, tenant e fingerprint** antes de assinar um grant Ed25519.
- **Envelope estrito:** `kind=F146-synthetic-noop-v1`, operação, empresa, fingerprint, geração, timestamp curto de 30 segundos, contador e cabeça do journal F145 e target fixo `synthetic-noop`. O verificador rejeita campos extras, tentativas de mudar tenant, nonce, target, assinatura, TTL e formato.
- **Processo filho separado:** `bin/lab/f146_synthetic_executor.php` lê somente um JSON por stdin, recebe por ambiente filtrado **apenas a chave pública do emissor** e um par sintético diferente para assinar seu próprio ledger. Não recebe a chave privada do emissor, não carrega Laravel, APIs de Ads ou PG, não possui qualquer função de veiculação ou cobrança.
- **Histórico de execução distinto:** `F146SyntheticExecutor` faz `flock` sobre a própria autorização terminal e registra, no seu próprio diário assinado F141, um evento por operação/fingerprint. Um checkpoint high-watermark independente `events / epoch / head` impede repetições mesmo quando o processo é reiniciado. O executor retorna `simulated=true` somente depois de escrever o recibo local; **`action_authorized=false` sempre**.
- **Falha fechada:** corrupção, truncamento do diário até prefixo legitimamente assinado, perda do checkpoint e falha entre o append e o checkpoint produzem resultado desconhecido e bloqueiam **todas** as próximas decisões, sem retry automático.
- **Testes:** CI herda a restauração conjunta de quatro bancos e o bypass SQL demonstrado pela F145. Depois executa testes próprios de subprocesso (isolamento sintético, grants falsos/vencidos, tenant, nonce, target `google-ads` recusado, idempotência após reiniciar, dois receipts A/B, alterações de checkpoint, truncamento e janela de crash), sem executar ações externas.

## Limitações importantes

**Esta é uma fronteira de software em laboratório, não segurança operacional implantada.** O processo filho roda no mesmo runner GitHub Actions; o ambiente filtrado reduz exposição de chaves de teste, mas não há isolamento de usuário do sistema operacional, contêiner independente, mTLS, IAM, KMS/HSM, serviço externo, credenciais exclusivas ou roteamento proibindo outros caminhos de Ads. Um operador do host com acesso ao sistema de arquivos ou às chaves sintéticas pode restaurar o **journal e seu checkpoint juntos**, assinar novos grants ou ignorar o executável inteiramente. Sem antirollback realmente imutável e independente, a prova continua limitada.

O vínculo assinado não representa consentimento de anunciante, aprovação de orçamento/campanha, credenciais delegadas OAuth Google/Meta, política de contas por empresa, ou autorização administrativa. A fase F145 ainda simula um sucesso SQL por fixtures e não autentica identidade de workload de produção. O executor **não envia anúncios**, mesmo quando retorna `synthetic_noop_recorded`. A ausência de APIs/SDKs é uma restrição do laboratório, não evidência de bloqueio efetivo de rede em produção.

A F146 não corrige a falta de 2PC/XA, crashes parciais entre bancos, restauração de todas as autoridades, indisponibilidade de fornecedor, políticas operacionais ou revisão independente DBA/AppSec. P0 / HT-R01 / SEC-R03 / T3: **NO-GO**. O código não deve ser mesclado à `main`, publicado ou acoplado a tokens reais.

## Próximo gate

F147: adicionar casos de corrida concorrente entre dois processos, injeção de indisponibilidade e política de quarentena; definir interface real de um executor com credenciais fora do alcance do app/SQL e autoridade não restaurável no mesmo domínio. Só considerar homologação quando AppSec/DBA independente revisar e houver testes de isolamento real, IAM/S2S e controle financeiro de anúncios.
