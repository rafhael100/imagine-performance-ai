# ADMIN-P0-01 — contrato administrativo v1 e motor de laboratório

Base: candidata PR410 `3b34173ea3aa368b7db3b748bdae099f2540102e`.
Referências: PERF-08 (aprovação por recurso/versão), PERF-10 (fonte/tempo/estado), PERF-11 (incidente permanece até resolução comprovada).

O Portal continua sendo autoridade de sessão humana, MFA, associação e papel. O motor recebe contexto derivado pelo servidor, nunca sessão do navegador, e não acessa o banco do Portal. Nesta versão o transporte é **código local pinado apenas em laboratório**, sem endpoint S2S. Não confundir isso com autenticação de serviço operacional.

`contracts/admin/v1.json` versiona consultas e comandos. O motor puro valida tenant, ator, papel, confirmação, MFA recente, revisão e transições. Proposta expirada, versão/digest diferentes, autoaprovação e execução pelo próprio aprovador são rejeitados. Aprovar orçamento ou financeiro registra decisão sintética, mas não permite execução. Somente recibo interno `internal_note` pode ser consumido uma vez por outro operador. Agente falho não pode ser retomado sem diagnóstico; pausa interna nunca pausa anúncio externo.

Financeiro reutiliza `MonthlyManagementFeeFloorV7Candidate`: R$490/canal, 20% ou piso R$170 por canal no mês cheio, mídia separada. ChatGPT indisponível fica fora do cálculo; mês parcial retorna pendência, nunca piso automático. Nenhum resultado é faturável.

O Portal deve persistir estado+evento atomicamente, validar idempotência por tenant/ator/ação e serializar revogação e concorrência. Esta biblioteca não oferece persistência ou autoridade por si só.

Teste: `php tests/administrative_lab_engine_test.php`; regressão V7 no workflow ADMIN P0. Testes CI são evidência laboratorial, não homologação operacional. DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.
