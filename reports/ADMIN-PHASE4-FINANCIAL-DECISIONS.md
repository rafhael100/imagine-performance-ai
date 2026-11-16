# Fase4 — matriz de decisão comercial, versão proposta 1

Nenhuma alternativa foi aprovada automaticamente. Baseline preservada: R$490 implantação por canal; gestão de20% sobre mídia verificada; mínimo R$170 por canal conforme contrato; verba de mídia separada. Sem extrato/consentimento/política válida: valor indisponível, nunca zero inventado. Cálculos abaixo são exemplos em BRL, não cobranças.

| Cenário | Alternativas e impacto ilustrativo | Decisão ainda necessária |
|---|---|---|
| Primeiro mês de10dias em mês de30, mídia verificada R$300 | mínimo integral: max(60,170)=R$170; mínimo proporcional: max(60,56,67)=R$60 | definir dias inclusivos, timezone e arredondamento; percentual sobre mídia real não deve ser prorrogado novamente sem política explícita |
| Inclusão de canal nos últimos5/30dias, mídia R$100 | mínimo integral R$170; proporcional max(20,28,33)=R$28,33; implantação separada R$490 conforme contrato | data efetiva, momento da ativação e incidência da implantação |
| Exclusão após15/30dias, mídia R$500 | mínimo integral R$170; proporcional max(100,85)=R$100 | dias elegíveis e encerramento contratual; não apagar histórico |
| Suspensão e reativação,20/30dias elegíveis, mídia R$400 | mínimo integral R$170; proporcional max(80,113,33)=R$113,33 | causa da suspensão, elegibilidade, eventual nova implantação; não presumir R$490 novamente |
| Extrato corrigido de R$1.000 para R$1.200 | gestão passa de R$200 para R$240 no exemplo, diferença R$40 | revisão ou ajuste posterior, competência, aceite e trilha; sem duplicar débito |
| Política alterada após revisão | fechamento anterior preservado; nova revisão vinculada ao digest da nova política | retroatividade e reaceite; revisão incompatível não pode ser reaproveitada |

Aprovar documento imutável com policy_id/version/digest, vigência, contrato, moeda, timezone, precisão/rounding, intervalos elegíveis e alçada de revisão. Testes devem cobrir fevereiro/bissexto, último dia, zero verificado versus ausência, eventos duplicados e revogação. O motor V7 existente continua bloqueando política parcial não aprovada.

Asaas/C6: não há credenciais/contratos de integração sandbox homologados nesta entrega. Adaptadores transacionais não foram ativados nem simulados como integração concluída. Antes de implementar execução, exigir endpoints/schemas oficiais, conta sandbox, autenticação de webhook, conciliação independente e idempotência durável por empresa/contrato/evento. Nenhuma emissão, consulta autenticada ou transação foi realizada. Contratação e decisão comercial continuam sob responsabilidade da Direção; nenhuma aprovação humana foi fabricada.
