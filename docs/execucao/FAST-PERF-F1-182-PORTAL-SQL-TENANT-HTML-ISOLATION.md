# FAST-PERF F1-182 — duas empresas fictícias em painéis HTML separados

**Base F181/PR #289. DRAFT, CI-only / NO-GO.**

A cadeia F179–F181 já demonstra a empresa fictícia A emitida via assinatura histórica Portal F29, nonce F124, PostgreSQL F100 com `session_user` e HTML F63 protegido por checksum local. Para testar os componentes visuais em ambiente **multiempresa**, F182 utiliza a segunda role SQL PostgreSQL `perf_f91_reader_b` já criada pelas migrações F91, sem criar emissor/assinatura B (que a F29 histórica não oferece no contrato de laboratório). O leitor original F100 consulta a role A e a role B **separadamente**, com RLS e sem parâmetro de tenant escolhido pelo chamador.

O teste compara os identificadores de contas, campanhas e revisões em ambos os resultados SQL; renderiza somente o DTO B pelo **PHP original F63 da candidata Portal**; verifica que os identificadores SQL B aparecem exclusivamente no HTML B, os A exclusivamente no HTML A da F181, e que o aviso “DEMONSTRAÇÃO NÃO HOMOLOGADA”, a política CSP sem rede/scripts e a ausência de formulários e controles executáveis estão preservados.

O GitHub Actions anexa **dois HTMLs independentes, ambos totalmente fictícios**, com retenção de 1 dia: A proveniente da cadeia assinada de laboratório F181 e B proveniente do login SQL fictício B. O objetivo é revisão visual e teste de isolamento do **backend PostgreSQL**, não autenticação de usuário ou S2S. O arquivo B **não** possui comprovação F29/F124 e não pode ser promovido como entrega assinada.

Limite arquitetural: F63 é apenas um renderizador estático de arquivos. Ele **não** identifica um usuário/empresa Portal por conta própria; nomes de arquivo, JSON e sessão SQL separada não são autorização IAM. Qualquer endpoint real deve obter a identidade Portal por meio confiável, decidir a empresa e o serviço elegível, conferir autorização/revogação independentemente, aplicar protocolo assinado e evitar acesso a arquivos de outras empresas. Nenhuma rota foi criada.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Sem merge dev/main, deploy VPS, Google/Meta Ads, clientes reais, pagamentos, cobranças ou custos externos.
