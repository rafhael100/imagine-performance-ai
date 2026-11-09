# FAST-PERF F1-47 — Observação única de autorização na autoridade externa

**Data:** 09/11/2026. Branch filha de F1-46, somente PostgreSQL descartável de CI, sem aplicação de produção.

## Lacuna F1-45/F1-46

A emissão na base externa verificava a quarentena, mas um token portador previamente emitido podia ser consumido no banco local após a fonte externa entrar em quarentena, se o estado local ainda estivesse liberado. A validade de 30s e o relógio de servidor limitavam a janela, porém **não revogavam imediatamente o token**.

## Recorte implementado

A nova função SQL `perf_f47_authority.observe` reside na **mesma instância PostgreSQL da quarentena F1-41 (porta 5433)**. Ela recebe o token de laboratório emitido por `perf_f46_issuer.mint_server` e, **na mesma transação**, executa a leitura `SELECT ... FOR SHARE` da linha de quarentena, verifica a época vigente, confere o HMAC com a chave privada desse mesmo banco, confronta o nonce ao livro de emissão, aplica relógio de banco/validade de 30s e registra recebimento único do nonce em `perf_f47_authority.observed`.

- O uso de `FOR SHARE` evita que um UPDATE administrativo da quarentena faça commit simultaneamente com uma observação ainda em andamento: a ordem de decisão é serializada **dentro dessa instância**.
- A função aceita **somente o marcador `claim` de leitura fictícia**, sem ID de campanha/outbox e sem geração. Um token `ack` ou qualquer operação de escrita é recusado. O retorno `observed_external_mock` **não é uma autorização para executar trabalho**.
- O novo papel `perf_f47_observer` só recebe EXECUTE na nova função; não lê chave, livro de emissão, livro de observação ou tabela de quarentena. A role antiga consumidora local F1-46 perde EXECUTE no CI, e a nova classe PHP não possui conexão/credencial do banco local.
- Testes negativos cobrem token inválido, assinatura adulterada, nonce repetido, emissão futura/expirada, papel errado, falta de privilégio, alteração de quarentena sob lock, token emitido ANTES da revogação, quarentena com banco local ainda liberado, e token antigo após recuperação em época nova.
- Em nenhum ponto se ativa `dispatch_enabled`, se confirma lease local ou se chama Google/Meta/CRM/n8n.

## Evidência adversarial esperada

Depois de emitir dois tokens, o CI deixa o SQL local em `released_mock:8` e coloca somente a autoridade externa em `quarantined:9`. **O novo caminho externo deve negar ambos os tokens pré-revogação**, sem gravar um recibo. Ele não depende do estado local. Após simular liberação na época 10, os tokens da época 8 continuam inválidos.

**A garantia é estritamente local ao novo caminho de observação na autoridade externa.** Não existe execução de campanhas nem integração entre este recibo e qualquer destino. A função antiga do banco local com papel revogado no CI não pode ser usada pela role antiga, mas administradores SQL ou outras credenciais privilegiadas ainda podem contornar os controles do laboratório. Se um componente armazenar a observação e executá-la depois, a segurança de revogação na hora do efeito não está demonstrada. As duas instâncias continuam restauráveis e a época pode voltar no tempo em restauração de ambas.

## Requisitos ainda abertos

Não confundir resultado favorável de CI com garantia de **linearização distribuída**: faltam um domínio monotônico resistente a restaurações, vínculo real com tenant/serviço/ator/campanha/contrato, autenticação mTLS e prova de posse, KMS/assinatura de produção, fonte autorizativa verdadeira, execução e idempotência nos provedores, autorização humana, auditoria AppSec/DBA, detecção de restore e protocolo de recuperação. Contagem de nonce/recebimento de leitura não equivale a exactly-once de efeitos remotos.

**Issues #102 e #115; P0, HT-R01, SEC-R03/T3 permanecem abertos.** Sem merge, `main`, fast-track, VPS, fornecedores pagos, publicação, cobrança, clientes ou efeitos externos.