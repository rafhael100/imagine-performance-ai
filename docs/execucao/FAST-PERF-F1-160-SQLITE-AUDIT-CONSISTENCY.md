# FAST-PERF F1-160 — relatório de consistência da auditoria de revisões

Base F159, PR #267. **Somente laboratório SQLite no GitHub Actions.**

A F160 adiciona `F160SyntheticSqliteAuditReader`, um leitor SQLite com `PRAGMA query_only=ON`, que verifica a sequência de eventos e a versão final de cada revisão fictícia. O mecanismo reconstrói o estado esperado a partir dos eventos `submitted_demo`, `changes_requested_demo`, `resubmitted_demo`, `approved_mock_only` e `rejected_mock_only`. A verificação impede que eventos com versões ausentes, ordem impossível ou estado divergente sejam tratados como histórico consistente.

Quando dados ou eventos não concordam, o resultado é `audit_inconsistent_fail_closed`, sem exportar linhas nem digest. O contrato também devolve um SHA-256 **meramente comparativo** do snapshot consistente, com `audit_verified_for_production=false`, `iam_verified=false`, `dispatch_enabled=false` e `billing_enabled=false`. O leitor não pode executar alterações SQL, mas um administrador com acesso direto ao arquivo ainda pode modificá-lo.

**Teste esperado-negativo obrigatório:** depois de apagar *ambas* as tabelas para o tenant A, um banco vazio apresenta-se como internamente consistente, embora os eventos originais tenham desaparecido. Portanto o teste demonstra, sem disfarces, que a F160 **não detecta apagamento/rollback simultâneo de histórico e estado**. Em produção seria necessária âncora confiável fora do domínio de desastre, política de retenção, monitoramento independente e trilha de evidência sob gestão autorizada.

O CI usa uma conexão SQL administrativa sintética para simular: status alterado sem evento, transição inválida, evento intermediário removido, identificador de revisor malformado, restauração e exclusão simultânea; também confirma que tenant B não é afetado. Executa regressões F157–F159.

**NO-GO P0/HT-R01/SEC-R03/T3. Sem IAM do Portal, segredos, Ads, faturamento, deploy, dados de clientes ou merge na main.** Próximo passo viável: construir exportação JSON/CSV somente se o leitor confirmar consistência, para servir à investigação de incidentes fictícios; não ativar recuperação automática.
