# Preparação de homologação na VPS — relatório sem alterações

Este script é um **pré-check read-only** para a VPS Leaseweb planejada (Ubuntu 24.04, 8 vCPU, 24 GB RAM, armazenamento NVMe). Ele **não depende de credenciais de fornecedores**, não provisiona serviços, não abre portas, não cria contêineres nem faz deploy. Foi desenvolvido enquanto a VPS não está disponível para acesso remoto.

Quando o proprietário obtiver acesso por SSH, dentro do checkout revisado e confiável do repositório, pode executar:

```bash
bash ops/homologacao/preflight-host-readonly.sh > preflight-imagine-performance.json
```

O JSON apresenta versão do SO, CPUs, RAM, disco, presença de Docker/Compose e acesso local ao daemon, sem inspecionar chaves, tokens ou dados dos clientes. O resultado é **sempre** `REQUIRES_HUMAN_REVIEW_NO_GO`, inclusive quando o hardware atende às expectativas. NÃO colar informações de acesso nem senhas em issues; compartilhar apenas o relatório após verificar seu conteúdo.

Antes de provisionar serviços: revisar o isolamento entre contêineres dos projetos Imagine, política de rede/segredos, criação de roles SQL, backups/restores, usuários de serviço mínimos, certificados TLS/mTLS, integridade de imagens, rate limiting, observabilidade, LGPD e governança AppSec/DBA. Não utilizar a VPS de desenvolvimento com contas de publicidade ou cobrança real.

O teste automatizado `ops/homologacao/test-preflight-host-readonly.sh` valida o esquema e a ausência de comandos mutantes no script. Testes no runner **não substituem** execução na VPS verdadeira.
