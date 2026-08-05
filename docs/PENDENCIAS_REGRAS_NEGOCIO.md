# Pendências — dependem de regras de negócio ainda não definidas

Este documento lista **somente** o que está tecnicamente pronto (infraestrutura, tabelas, telas de configuração) mas que precisa de informação dos responsáveis pra ser preenchido com o conteúdo real. Nada aqui bloqueia o uso do sistema hoje.

## 1. Checklist definitivo de atividades por Projeto

**Onde configurar quando a regra chegar:** `/admin/projetos/{id}/requisitos`

Infraestrutura pronta (tabela `projeto_requisitos`): nome, tipo (foto/texto/lista de presença/documento/vídeo/confirmação/outro), obrigatório ou não, quantidade mínima, instrução, ordem, ativo/inativo. Aparece como checklist informativo pro professor ao registrar uma atividade em `/professor/atividades/nova`.

**Falta:** a lista real de exigências de cada projeto:
- Futebol
- Transformando Vidas
- Vida em Movimento
- (demais projetos que existirem)

## 2. Requisitos de fotos por Projeto

Mesma infraestrutura do item 1 (tipo=`foto`, quantidade mínima). Falta saber, por projeto: quantas fotos são exigidas por aula/atividade, e o que cada foto precisa mostrar.

## 3. Requisitos oficiais completos de Prestação de Contas

**Onde consultar hoje:** `/admin/prestacao-contas/{termoId}` — já consolida automaticamente tudo que o sistema tem registrado (inscritos, aulas previstas/realizadas/justificadas/canceladas, chamadas, atividades, evidências, depoimentos, anexos do termo) por núcleo e período.

**Falta:** o checklist oficial de documentos/comprovações exigidos pelo Ministério ou órgão financiador para fechar a prestação de contas formalmente. Quando definido, entra pela mesma estrutura configurável do item 1 (associada ao projeto, ou pode virar uma variação específica associada ao Termo de Fomento se a regra exigir).

## 4. Campos administrativos definitivos do Termo de Fomento

**Onde está hoje:** `/admin/projetos/{id}/termos` — número, descrição, data início/fim, status (ativo/encerrado/suspenso), observações, anexos (PDF).

**Falta:** saber se existem campos administrativos obrigatórios adicionais (valor do repasse, órgão financiador, número de processo administrativo, contrapartida, metas contratuais etc.) que o Termo de Fomento precisa ter oficialmente.

## 5. Institutos reais dos projetos existentes (ação manual necessária, não é regra de negócio — é dado)

A migration que introduziu Institutos moveu automaticamente **todo projeto já cadastrado antes desta refatoração** para um instituto placeholder chamado `[Migração] Revisar institutos`, porque o sistema não tinha essa informação antes. Isso não é uma pendência de regra — é uma ação que o Super Admin precisa fazer uma vez:

1. Ir em `/admin/institutos` → criar o(s) instituto(s) real(is).
2. Ir em `/admin/projetos` → editar cada projeto e trocar o instituto para o correto.

## 6. Eventuais regras diferentes de outros projetos

Se novos projetos tiverem fluxos operacionais muito diferentes dos já mapeados (Futebol, Transformando Vidas, Vida em Movimento), documentar aqui assim que aparecerem — a arquitetura (checklist configurável por projeto, escopo por instituto/projeto/núcleo) já foi desenhada pra acomodar isso sem precisar de mudança estrutural, só configuração.
