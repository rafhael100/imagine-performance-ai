# FAST-PERF F1-167 — gerações de escopo para revisões após reativação

**Base F166 (PR #274). Somente PostgreSQL16 descartável de CI. DRAFT/NO-GO.**

A F166 esconde toda revisão de uma campanha que já sofreu retirada alguma vez, mesmo que a campanha seja depois reativada. Isso impede ressuscitar uma aprovação antiga, mas também esconde as revisões **novas** de uma campanha legitimamente reativada.

F167 acrescenta a coluna `scope_epoch` à tabela de campanhas e à de revisões fictícias. Em cada mudança real `managed=true ↔ false`, uma trigger SQL do proprietário do schema incrementa monotonamente a geração da campanha, mantendo o lock F165 de serialização. Em cada INSERT de revisão, outra trigger fixa `scope_epoch` no valor atual do banco, ignorando um epoch enviado pelo chamador. Uma revisão só pode avançar de estado enquanto sua geração continuar sendo a geração ativa da campanha. A camada de views F166 agora mostra **somente revisões do epoch atual** e conta, sem detalhes, gerações antigas ou campanhas retiradas.

**Atualização conservadora:** todo review pré-F167 recebe `scope_epoch=0`, equivalente a `legacy_unverified`. Nenhum atributo desconhecido de revisão antiga é adivinhado para atribuir confiança, mesmo se a campanha ainda está sob gestão. Para as campanhas existentes, `scope_epoch` é inicializado com 1 + quantidade de eventos conhecidos de retirada/reativação registrados na F165; épocas de eventos históricos usam um marcador legado. Isso só ocorre em schema descartável, **não se deve executar a migration em produção**.

O CI executa os testes anteriores F162–F166 primeiro para testar o upgrade em banco povoado. Após adicionar as colunas, valida que reviews legados ficam ocultos; cria campanhas e reviews novos, passa por retirada (epoch2) e reativação (epoch3), comprova que a revisão antiga permanece em quarentena, mas uma **nova** revisão criada no epoch3 aparece e pode receber apenas status `approved_mock_only`. Tentativas de atualizar revisões anteriores, manipular `scope_epoch` via INSERT ou UPDATE, fingir epoch do administrador e cruzar empresas são recusadas ou ignoradas por triggers e RLS. Os eventos de gestão registram 1→2→3.

**Limitações e riscos:** a autoridade de reativação continua sendo o administrador do banco sintético, não uma aprovação humana ou contrato real; o superusuário ainda pode mudar tabelas e logs. `scope_epoch` não é autoridade externa antirrollback: restauração conjunta de campanhas, revisões e histórico permite recuar épocas. Não há API, IAM Portal/MFA/S2S, cliente, OAuth Meta/Google, publicação, faturamento ou orçamento. Nenhuma revisão de AppSec/DBA independente foi realizada.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Sem merge main, deploy VPS, usuários reais, campanhas ou despesas.**
