# Sistema de Gestão de Núcleos — Documentação

Última atualização: refatoração multi-instituto (institutos → projetos → termos de fomento → núcleos → equipe → cronograma → aulas → chamadas → atividades → evidências → relatórios → prestação de contas).

## 1. Arquitetura

PHP puro (sem framework), MVC manual:

- **Front controller**: `public/index.php` — roteamento por array associativo (`$routes['GET'|'POST'][padrão] = [Controller, método]`), sem classe Router dedicada.
- **Models**: não existem classes de entidade — acesso a dados é direto via `Database::getInstance()` (PDO singleton) dentro de cada controller.
- **Views**: `app/views/**/*.php`, padrão `ob_start()`/`$content`/`require layouts/app.php` (sem template engine).
- **Banco**: MySQL 8 / MariaDB, `database/schema.sql` (instalação nova) + `database/aplicar_migrations.php` (script idempotente que roda a cada deploy via `.cpanel.yml` — verifica o que já existe antes de alterar, nunca duplica, nunca falha em rodar de novo).
- **Deploy**: cPanel Git™ Version Control, repo clonado direto na doc root. `.cpanel.yml` roda `aplicar_migrations.php` automaticamente a cada "Deploy HEAD Commit" — não precisa de terminal nem phpMyAdmin pra atualizar o banco.

## 2. Hierarquia de dados

```
Instituto
 └─ Projeto (instituto_id obrigatório)
     ├─ Termo de Fomento (número, período, status, anexos)
     ├─ Checklist configurável (projeto_requisitos — infraestrutura pronta, exigências reais pendentes)
     └─ Núcleo (projeto_id — instituto vem por herança via join, sem coluna redundante)
         ├─ Alunos (inscritos)
         ├─ Grade de horários / Cronograma (grade_horarios — professor_id, dia, horário)
         ├─ Aulas previstas (aulas_previstas — snapshot gerado do cronograma, nunca reescrito retroativamente)
         ├─ Chamadas + presenças (chamadas, chamada_presencas, chamada_presenca_historico)
         ├─ Atividades diárias + evidências (atividades, atividade_evidencias)
         ├─ Check-ins de geolocalização (checkins)
         └─ Depoimentos (depoimentos)
```

Toda tabela operacional (chamadas, aulas_previstas, atividades, checkins, depoimentos, alunos, grade_horarios) tem `nucleo_id` — esse é o ponto de escopo usado em todo o sistema; institutos e projetos são resolvidos por join a partir dele.

## 3. Permissões (o que o usuário pode fazer)

Tabelas: `permissoes` (catálogo de ~35 chaves, ex. `nucleos.editar`, `chamadas.corrigir`) e `usuario_permissoes` (concessões por usuário). Motor em `app/helpers/Permissao.php`:

```php
Permissao::has($usuarioId, 'nucleos.editar'): bool   // super_admin sempre true
Permissao::requer('nucleos.editar');                  // 403 se não tiver
```

Cada controller administrativo chama `Auth::requireAdminArea()` (super_admin OU gestor autenticado) **e** `Permissao::requer('modulo.acao')` — nunca só um dos dois. A interface (sidebar, botões) também respeita a permissão, mas isso é só UX: a proteção real é sempre no servidor.

## 4. Escopos (onde o usuário pode atuar)

Tabela `escopos_usuario` (tipo: `instituto`|`projeto`|`nucleo`, referencia_id). Motor em `app/helpers/Escopo.php`, com herança de cima pra baixo:

```php
Escopo::nucleosPermitidos($usuarioId): array   // IDs de núcleo que o usuário enxerga
Escopo::whereIn($ids, 'n.id'): [sql, params]    // pra injetar em qualquer query existente
```

Escopo de instituto → libera todos os projetos e núcleos dele. Escopo de projeto → libera todos os núcleos dele. Escopo de núcleo → só aquele núcleo. Professores tradicionais (via `nucleo_professores`) continuam funcionando sem precisar de linha em `escopos_usuario` — o motor consulta essa tabela como fallback de compatibilidade.

Toda tela administrativa filtra queries com `Escopo::whereIn(...)`, e toda ação sobre um registro específico (editar, excluir, cancelar) verifica `Escopo::podeAcessarX(...)` antes de agir — nunca confia só no ID vir "certo" da URL.

## 5. Usuários e hierarquia

`usuarios.perfil`: `super_admin` | `gestor` | `professor` | `aluno`.
`usuarios.cargo` (só rótulo organizacional, quem manda é permissão+escopo): `presidente`, `coordenador_geral`, `coordenador_projeto`, `coordenador_nucleo`, `professor`, `monitor`, `colaborador`.

Cadastro de equipe em `/admin/professores` (tela "Equipe"). Regras de hierarquia (Etapa 9-10), aplicadas em `AdminProfessoresController`:

- Só super_admin pode atribuir o cargo `presidente`.
- Presidente/coordenador geral podem criar/editar coordenadores de projeto/núcleo, professores, monitores, colaboradores.
- Coordenador de projeto/núcleo só cria/edita professores, monitores, colaboradores.
- **Nunca** dá pra conceder a outra pessoa uma permissão ou escopo que você mesmo não tem — `Permissao::salvar()` e `Escopo::salvar()` filtram automaticamente qualquer tentativa de escalonamento, mesmo que alguém manipule o formulário manualmente.
- Ninguém edita/inativa um cargo igual ou superior ao próprio (`podeGerenciar()`), exceto super_admin.

Cargo `professor` sincroniza automaticamente `nucleo_professores` (compatibilidade com o fluxo tradicional de frequência/agenda).

## 6. Cronograma e aulas

Igual à sessão anterior desta refatoração (não mudou): `grade_horarios` é a regra recorrente; `aulas_previstas` é a ocorrência concreta, gerada por cron (`cron/processar_cronograma.php`) ou lazy no acesso do professor, e nunca reescrita quando o cronograma muda depois. Status: `prevista` → `realizada`/`justificativa_pendente` → (`justificada`/`cancelada`). Só quem tem `cronograma.administrar` mexe no cronograma — professor nunca edita o próprio horário.

## 7. Chamada, correção e histórico

`chamadas` + `chamada_presencas` (presença por aluno). Correção de presença só com permissão `chamadas.corrigir` (tela `/admin/chamadas/{id}`), sempre gera linha em `chamada_presenca_historico` (de/para/quem/quando) e no `audit_log`.

## 8. Fluxo sem internet (Etapa 15-16)

Justificativa de ausência ganhou `tipo` (sem_internet, chuva, problema_local, imprevisto, outro). Quando `tipo=sem_internet`:

1. Professor envia a justificativa normalmente (libera o bloqueio operacional).
2. No histórico de justificativas aparece "Lançar chamada" pra aquela aula específica.
3. `/professor/frequencia/nova?retroativa={aula_prevista_id}` — a **data é travada** na data real da aula (nunca confia em input do usuário), permite anexar fotos de evidência (`Upload::image`, sem alterar metadados/data de criação do arquivo) e, ao salvar, marca `chamadas.registrado_retroativamente=1` + vincula `justificativa_ausencia_id`.
4. Data da aula (`data_aula`), data real do lançamento (`criado_em`, sempre o timestamp real do servidor) e motivo ficam todos preservados e distintos — nunca se falsifica quando o lançamento aconteceu de verdade.

## 9. Evidências geolocalizadas

Evidências (fotos) entram como upload comum — o app externo do professor já grava data/hora/latitude/longitude na própria imagem, o sistema não tenta extrair nem alterar esses metadados. `atividade_evidencias.latitude/longitude/capturado_em` existem no schema pra uso futuro se algum dia for necessário ler EXIF, mas hoje ficam `NULL` (infra pronta, não usada — evita inventar parsing de EXIF sem necessidade real).

## 10. Check-in geolocalizado (auditado, já existia)

`CheckinController::store()` — validação sempre no backend (Haversine, raio fixo de 200m), nunca confia no frontend. Status: `dentro_raio`/`fora_raio`/`sem_coordenadas`. Agora também salva `precisao_m` (accuracy do GPS do navegador, quando disponível).

## 11. Atividades diárias e checklist configurável

`/professor/atividades` — professor registra o que aconteceu (data, horário, descrição, observações, fotos). O checklist do projeto (`/admin/projetos/{id}/requisitos`, configurado pelo admin) aparece como guia informativo na tela de registro — **nunca bloqueia**, porque as exigências reais de cada projeto (Futebol, Transformando Vidas, Vida em Movimento etc.) ainda não foram definidas pelos responsáveis.

## 12. Relatórios e Prestação de Contas

- `/admin/relatorios` — landing que organiza Inscritos (novo, consolidado por Instituto→Projeto→Núcleo), Frequência (novo, exportável em CSV), e linka pro que já existe (Aulas, Check-ins, Monitoramento, Exportação de alunos) sem duplicar.
- `/admin/prestacao-contas` — por Termo de Fomento, consolida automaticamente tudo que já foi registrado na operação (inscritos, aulas previstas/realizadas/justificadas/canceladas, chamadas, atividades, evidências, depoimentos, anexos do termo) por núcleo e período. Botão "Imprimir/Salvar PDF" usa a impressão nativa do navegador (`window.print()` + CSS `@media print`) — PDF confiável sem depender de biblioteca externa frágil.
- O checklist oficial de exigências de prestação de contas ainda não foi definido — quando chegar, entra via `projeto_requisitos` (já configurável).

## 13. Auditoria

`audit_log` registra usuário, ação, tabela, registro, IP, user-agent, e agora também `dados_anteriores`/`dados_novos` (JSON) para correções sensíveis (ex. correção de presença). `Security::auditLog()` aceita esses dois parâmetros extras opcionalmente — chamadas antigas continuam funcionando sem eles.

## 14. Arquivos principais

- `app/helpers/Permissao.php`, `app/helpers/Escopo.php` — motor de permissões e escopo.
- `app/controllers/Admin{Institutos,Projetos,Nucleos,Professores,TermosFomento,ProjetoRequisitos,Cronograma,Aulas,Chamadas,Monitor,Checkins,Exportacao,Relatorios,PrestacaoContas,Depoimentos,Evidencias}Controller.php`
- `app/controllers/Professor{Agenda,Frequencia,Atividades,Alunos}Controller.php`, `JustificativaController.php`
- `database/aplicar_migrations.php` — schema completo, idempotente.
- `database/test_cronograma.php`, `database/test_multi_instituto.php` — testes (CLI, transação com rollback, seguros contra o banco de produção).

## 15. Como rodar os testes

```bash
php database/test_cronograma.php
php database/test_multi_instituto.php
```

Ambos rodam dentro de uma transação e sempre fazem ROLLBACK no final — nunca alteram dados reais, seguros de rodar em produção quantas vezes quiser.

## 16. Limitação conhecida (não corrigida nesta refatoração)

O fluxo operacional de frequência (`ProfessorFrequenciaController`) ainda assume **um único núcleo por professor** (`nucleo_professores` com `LIMIT 1`), herdado do sistema original. O cronograma e a equipe já suportam múltiplos núcleos por professor (via `escopos_usuario` e `grade_horarios.professor_id`), mas se o admin atribuir um professor a dois núcleos diferentes, ele só consegue bater chamada no primeiro. Corrigir isso é uma mudança de escopo maior (toda a área operacional do professor assumiria múltiplos núcleos) — fora do pedido desta refatoração, documentado aqui para não virar surpresa.
