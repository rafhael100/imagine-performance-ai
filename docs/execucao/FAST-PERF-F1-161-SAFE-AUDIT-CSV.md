# FAST-PERF F1-161 — CSV de auditoria fictícia com pré-verificação obrigatória

Base F160, PR #268. **Somente CI em SQLite temporário; NO-GO.**

O `F161SyntheticAuditCsvExporter` não recebe flag de sucesso fornecida por um chamador: cada exportação chama novamente o verificador de eventos F160, que lê as tabelas SQLite e compara transições, versões e estado de revisão. Se a auditoria estiver inconsistente, indisponível ou fora dos limites, a exportação é recusada sem divulgar dados parciais. Os CSVs são UTF-8 BOM, ponto-e-vírgula, contêm somente identidades de fixture e repetem em cada linha `NAO E APROVACAO REAL`, `SEM ANUNCIOS` e `SEM COBRANCA`; o digest local SHA-256 é rotulado `nao_autenticado`.

O CI demonstra exportação consistente para a empresa A, separação da empresa B, um tenant fictício vazio e rejeição de tenant malicioso. Simula alteração do estado sem evento e remoção do evento intermediário: as tentativas de exportação de A são negadas, enquanto a exportação de B permanece estável. Também preserva o teste negativo de **exclusão conjunta de eventos e estado**, que permite exportar um CSV aparentemente vazio e consistente — prova de que SQLite/CSV não fornecem antirrollback confiável.

Não há exportação de arquivos reais a clientes, nem API HTTP, nem identidade Portal, mídia paga, contabilidade, VPS ou fornecedor. Os eventos sintéticos são legíveis somente neste CI. O mecanismo não foi auditado por AppSec/DBA independente e não substitui WORM/offsite, trilha externa ou controle de acesso operacional.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Nenhum merge na main, deploy, anúncio real, cobrança ou despesa.**
