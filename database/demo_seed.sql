-- Gestao de Nucleos - banco autocontido para demonstracao
-- Senha de todos os usuarios demo: Demo@2026
-- Gerado com password_hash('Demo@2026', PASSWORD_BCRYPT, ['cost' => 12])

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS notificacoes_log;
DROP TABLE IF EXISTS forum_posts;
DROP TABLE IF EXISTS forum_topicos;
DROP TABLE IF EXISTS comunicados;
DROP TABLE IF EXISTS materiais;
DROP TABLE IF EXISTS grade_horarios;
DROP TABLE IF EXISTS chamada_presencas;
DROP TABLE IF EXISTS chamadas;
DROP TABLE IF EXISTS convites;
DROP TABLE IF EXISTS alunos;
DROP TABLE IF EXISTS nucleo_professores;
DROP TABLE IF EXISTS nucleos;
DROP TABLE IF EXISTS projetos;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(180) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  telefone VARCHAR(20) DEFAULT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  descricao TEXT DEFAULT NULL,
  redes_sociais JSON DEFAULT NULL,
  perfil ENUM('super_admin','professor','aluno') NOT NULL DEFAULT 'aluno',
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_perfil_status (perfil, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projetos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(150) NOT NULL,
  descricao TEXT DEFAULT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nucleos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  projeto_id INT UNSIGNED NOT NULL,
  nome VARCHAR(150) NOT NULL,
  municipio VARCHAR(100) NOT NULL,
  estado CHAR(2) NOT NULL DEFAULT 'RJ',
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projeto_id (projeto_id),
  KEY idx_status (status),
  CONSTRAINT fk_nucleos_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nucleo_professores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nucleo_professor (nucleo_id, usuario_id),
  KEY idx_usuario_id (usuario_id),
  CONSTRAINT fk_np_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_np_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alunos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(180) DEFAULT NULL,
  telefone VARCHAR(20) DEFAULT NULL,
  whatsapp VARCHAR(20) DEFAULT NULL,
  endereco_completo VARCHAR(255) DEFAULT NULL,
  cidade VARCHAR(100) DEFAULT NULL,
  cep VARCHAR(9) DEFAULT NULL,
  data_nascimento DATE DEFAULT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nucleo_id (nucleo_id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_status (status),
  CONSTRAINT fk_alunos_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_alunos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE convites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  tipo ENUM('professor','aluno') NOT NULL,
  nucleo_id INT UNSIGNED NOT NULL,
  criado_por INT UNSIGNED NOT NULL,
  status ENUM('pendente','usado','expirado') NOT NULL DEFAULT 'pendente',
  expira_em DATETIME NOT NULL,
  usado_em DATETIME DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_nucleo_id (nucleo_id),
  KEY idx_status (status),
  KEY idx_expira_em (expira_em),
  CONSTRAINT fk_convites_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_convites_criado FOREIGN KEY (criado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chamadas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED NOT NULL,
  professor_id INT UNSIGNED NOT NULL,
  data_aula DATE NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nucleo_id (nucleo_id),
  KEY idx_professor_id (professor_id),
  KEY idx_data_aula (data_aula),
  CONSTRAINT fk_chamadas_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_chamadas_professor FOREIGN KEY (professor_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chamada_presencas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chamada_id INT UNSIGNED NOT NULL,
  aluno_id INT UNSIGNED NOT NULL,
  presente TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chamada_aluno (chamada_id, aluno_id),
  KEY idx_aluno_id (aluno_id),
  CONSTRAINT fk_cp_chamada FOREIGN KEY (chamada_id) REFERENCES chamadas (id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_horarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED NOT NULL,
  dia_semana TINYINT NOT NULL,
  horario_inicio TIME NOT NULL,
  horario_fim TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_nucleo_id (nucleo_id),
  CONSTRAINT fk_gh_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materiais (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED DEFAULT NULL,
  projeto_id INT UNSIGNED DEFAULT NULL,
  titulo VARCHAR(200) NOT NULL,
  tipo ENUM('pdf','imagem','link') NOT NULL,
  arquivo_url VARCHAR(512) NOT NULL,
  criado_por INT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nucleo_id (nucleo_id),
  KEY idx_projeto_id (projeto_id),
  KEY idx_criado_por (criado_por),
  CONSTRAINT fk_mat_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_mat_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id),
  CONSTRAINT fk_mat_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comunicados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  mensagem TEXT NOT NULL,
  enviado_por INT UNSIGNED NOT NULL,
  destinatario_tipo ENUM('todos','projeto','nucleo','aluno') NOT NULL,
  destinatario_id INT UNSIGNED DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enviado_por (enviado_por),
  KEY idx_destinatario_tipo (destinatario_tipo),
  CONSTRAINT fk_com_enviado FOREIGN KEY (enviado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_topicos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nucleo_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  fixado TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nucleo_id (nucleo_id),
  KEY idx_usuario_id (usuario_id),
  CONSTRAINT fk_ft_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos (id),
  CONSTRAINT fk_ft_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topico_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  conteudo TEXT NOT NULL,
  curtidas INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_topico_id (topico_id),
  KEY idx_usuario_id (usuario_id),
  CONSTRAINT fk_fp_topico FOREIGN KEY (topico_id) REFERENCES forum_topicos (id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notificacoes_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo VARCHAR(80) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  enviado_para VARCHAR(180) DEFAULT NULL,
  status ENUM('enviado','erro') NOT NULL DEFAULT 'enviado',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tipo (tipo),
  KEY idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED DEFAULT NULL,
  acao VARCHAR(80) NOT NULL,
  tabela_afetada VARCHAR(80) DEFAULT NULL,
  registro_id INT UNSIGNED DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(512) DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_acao (acao),
  KEY idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(180) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  sucesso TINYINT(1) NOT NULL DEFAULT 0,
  tentativa_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_tentativa (email, tentativa_em),
  KEY idx_ip_tentativa (ip, tentativa_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

SET @demo_hash = '$2y$12$yPAhsqvpyuLE.1l44T3ea.6UfQ2e9vyMCoJuhW5ad6lCSUx1BZsCm';

INSERT INTO usuarios (id, nome, email, senha_hash, telefone, perfil, status) VALUES
  (1, 'Welbert Pedro', 'admin@demo.com', @demo_hash, '(21) 99900-0001', 'super_admin', 'ativo'),
  (2, 'Prof. Carlos Mendes', 'professor1@demo.com', @demo_hash, '(22) 99900-0002', 'professor', 'ativo'),
  (3, 'Profa. Ana Lima', 'professor2@demo.com', @demo_hash, '(22) 99900-0003', 'professor', 'ativo'),
  (4, 'Joao Silva', 'aluno1@demo.com', @demo_hash, '(22) 98800-0001', 'aluno', 'ativo'),
  (5, 'Maria Souza', 'aluno2@demo.com', @demo_hash, '(22) 98800-0002', 'aluno', 'ativo'),
  (6, 'Pedro Costa', 'aluno3@demo.com', @demo_hash, '(22) 98800-0003', 'aluno', 'ativo');

INSERT INTO projetos (id, nome, descricao, status) VALUES
  (1, 'Friburgo em Movimento', 'Atividades esportivas e qualidade de vida em Nova Friburgo e regiao.', 'ativo'),
  (2, 'Judo Infantil', 'Formacao esportiva e cidadania por meio do judo.', 'ativo'),
  (3, 'Brasil de Talentos', 'Desenvolvimento de jovens talentos esportivos fluminenses.', 'ativo');

INSERT INTO nucleos (id, projeto_id, nome, municipio, estado, status) VALUES
  (1, 1, 'Friburgo em Movimento - Nova Friburgo', 'Nova Friburgo', 'RJ', 'ativo'),
  (2, 2, 'Judo Infantil - Cardoso Moreira', 'Cardoso Moreira', 'RJ', 'ativo'),
  (3, 3, 'Brasil de Talentos - Macae', 'Macae', 'RJ', 'ativo'),
  (4, 1, 'Friburgo em Movimento - Itaperuna', 'Itaperuna', 'RJ', 'ativo'),
  (5, 2, 'Judo Infantil - Campos', 'Campos', 'RJ', 'ativo'),
  (6, 3, 'Brasil de Talentos - Petropolis', 'Petropolis', 'RJ', 'ativo');

INSERT INTO nucleo_professores (nucleo_id, usuario_id) VALUES
  (1, 2), (3, 2), (5, 2),
  (2, 3), (4, 3), (6, 3);

INSERT INTO alunos
  (id, nucleo_id, usuario_id, nome, email, telefone, whatsapp, endereco_completo, cidade, cep, data_nascimento, status)
VALUES
  (1, 1, 4, 'Joao Silva', 'aluno1@demo.com', '(22) 98800-0001', '(22) 98800-0001', 'Rua das Flores, 10', 'Nova Friburgo', '28600-001', '2011-02-14', 'ativo'),
  (2, 1, 5, 'Maria Souza', 'aluno2@demo.com', '(22) 98800-0002', '(22) 98800-0002', 'Rua do Sol, 25', 'Nova Friburgo', '28600-002', '2010-06-28', 'ativo'),
  (3, 1, 6, 'Pedro Costa', 'aluno3@demo.com', '(22) 98800-0003', '(22) 98800-0003', 'Av. Central, 80', 'Nova Friburgo', '28600-003', '2012-09-10', 'ativo'),
  (4, 2, NULL, 'Beatriz Alves', 'beatriz@demo.com', '(22) 98800-0004', '(22) 98800-0004', 'Rua A, 15', 'Cardoso Moreira', '28180-001', '2013-03-21', 'ativo'),
  (5, 2, NULL, 'Gabriel Rocha', 'gabriel@demo.com', '(22) 98800-0005', '(22) 98800-0005', 'Rua B, 20', 'Cardoso Moreira', '28180-002', '2011-07-17', 'ativo'),
  (6, 2, NULL, 'Laura Martins', 'laura@demo.com', '(22) 98800-0006', '(22) 98800-0006', 'Rua C, 30', 'Cardoso Moreira', '28180-003', '2012-11-05', 'ativo'),
  (7, 3, NULL, 'Rafael Gomes', 'rafael@demo.com', '(22) 98800-0007', '(22) 98800-0007', 'Rua D, 12', 'Macae', '27900-001', '2010-01-29', 'ativo'),
  (8, 3, NULL, 'Sofia Ribeiro', 'sofia@demo.com', '(22) 98800-0008', '(22) 98800-0008', 'Rua E, 18', 'Macae', '27900-002', '2013-06-18', 'ativo'),
  (9, 3, NULL, 'Lucas Fernandes', 'lucas@demo.com', '(22) 98800-0009', '(22) 98800-0009', 'Rua F, 22', 'Macae', '27900-003', '2012-12-02', 'ativo'),
  (10, 4, NULL, 'Helena Barros', 'helena@demo.com', '(22) 98800-0010', '(22) 98800-0010', 'Rua G, 44', 'Itaperuna', '28300-001', '2011-04-11', 'ativo'),
  (11, 4, NULL, 'Matheus Nunes', 'matheus@demo.com', '(22) 98800-0011', '(22) 98800-0011', 'Rua H, 55', 'Itaperuna', '28300-002', '2010-08-30', 'ativo'),
  (12, 5, NULL, 'Isabela Castro', 'isabela@demo.com', '(22) 98800-0012', '(22) 98800-0012', 'Rua I, 60', 'Campos', '28000-001', '2012-05-09', 'ativo'),
  (13, 5, NULL, 'Davi Moreira', 'davi@demo.com', '(22) 98800-0013', '(22) 98800-0013', 'Rua J, 70', 'Campos', '28000-002', '2013-10-25', 'ativo'),
  (14, 6, NULL, 'Livia Azevedo', 'livia@demo.com', '(24) 98800-0014', '(24) 98800-0014', 'Rua K, 75', 'Petropolis', '25600-001', '2011-06-03', 'ativo'),
  (15, 6, NULL, 'Enzo Teixeira', 'enzo@demo.com', '(24) 98800-0015', '(24) 98800-0015', 'Rua L, 90', 'Petropolis', '25600-002', '2012-12-19', 'ativo');

INSERT INTO grade_horarios (nucleo_id, dia_semana, horario_inicio, horario_fim) VALUES
  (1, 1, '08:00', '09:30'), (1, 3, '08:00', '09:30'),
  (2, 2, '09:00', '10:30'), (2, 4, '09:00', '10:30'),
  (3, 1, '14:00', '15:30'), (3, 5, '14:00', '15:30'),
  (4, 2, '15:00', '16:30'), (4, 4, '15:00', '16:30'),
  (5, 3, '17:00', '18:30'), (5, 6, '09:00', '10:30'),
  (6, 1, '18:00', '19:30'), (6, 5, '18:00', '19:30');

INSERT INTO chamadas (id, nucleo_id, professor_id, data_aula) VALUES
  (1,1,2,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (2,1,2,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (3,1,2,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (4,1,2,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (5,1,2,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (6,1,2,DATE_SUB(CURDATE(),INTERVAL 5 DAY)),
  (7,2,3,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (8,2,3,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (9,2,3,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (10,2,3,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (11,2,3,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (12,2,3,DATE_SUB(CURDATE(),INTERVAL 5 DAY)),
  (13,3,2,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (14,3,2,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (15,3,2,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (16,3,2,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (17,3,2,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (18,3,2,DATE_SUB(CURDATE(),INTERVAL 5 DAY)),
  (19,4,3,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (20,4,3,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (21,4,3,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (22,4,3,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (23,4,3,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (24,4,3,DATE_SUB(CURDATE(),INTERVAL 5 DAY)),
  (25,5,2,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (26,5,2,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (27,5,2,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (28,5,2,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (29,5,2,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (30,5,2,DATE_SUB(CURDATE(),INTERVAL 5 DAY)),
  (31,6,3,DATE_SUB(CURDATE(),INTERVAL 80 DAY)), (32,6,3,DATE_SUB(CURDATE(),INTERVAL 65 DAY)), (33,6,3,DATE_SUB(CURDATE(),INTERVAL 50 DAY)), (34,6,3,DATE_SUB(CURDATE(),INTERVAL 35 DAY)), (35,6,3,DATE_SUB(CURDATE(),INTERVAL 20 DAY)), (36,6,3,DATE_SUB(CURDATE(),INTERVAL 5 DAY));

-- Nucleo 1: frequencia alta e estavel.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (1,1,1),(1,2,1),(1,3,1),(2,1,1),(2,2,1),(2,3,1),(3,1,1),(3,2,1),(3,3,1),
  (4,1,1),(4,2,1),(4,3,1),(5,1,1),(5,2,1),(5,3,1),(6,1,1),(6,2,1),(6,3,1);

-- Nucleo 2: queda progressiva para destacar alertas do dashboard.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (7,4,1),(7,5,1),(7,6,1),(8,4,1),(8,5,1),(8,6,1),(9,4,1),(9,5,1),(9,6,0),
  (10,4,1),(10,5,1),(10,6,0),(11,4,1),(11,5,0),(11,6,0),(12,4,0),(12,5,1),(12,6,0);

-- Nucleo 3: desempenho oscilante.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (13,7,1),(13,8,1),(13,9,0),(14,7,1),(14,8,1),(14,9,1),(15,7,0),(15,8,1),(15,9,1),
  (16,7,1),(16,8,1),(16,9,1),(17,7,1),(17,8,0),(17,9,1),(18,7,1),(18,8,1),(18,9,1);

-- Nucleo 4: queda moderada.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (19,10,1),(19,11,1),(20,10,1),(20,11,1),(21,10,1),(21,11,1),
  (22,10,1),(22,11,0),(23,10,0),(23,11,1),(24,10,1),(24,11,0);

-- Nucleo 5: frequencia alternada.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (25,12,1),(25,13,0),(26,12,1),(26,13,1),(27,12,0),(27,13,1),
  (28,12,1),(28,13,1),(29,12,1),(29,13,0),(30,12,1),(30,13,1);

-- Nucleo 6: frequencia alta e estavel.
INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES
  (31,14,1),(31,15,1),(32,14,1),(32,15,1),(33,14,1),(33,15,1),
  (34,14,1),(34,15,1),(35,14,1),(35,15,1),(36,14,1),(36,15,1);

INSERT INTO materiais (nucleo_id, projeto_id, titulo, tipo, arquivo_url, criado_por) VALUES
  (1, NULL, 'Aquecimento antes do treino', 'link', 'https://www.youtube.com/watch?v=R0mMyV5OtcM', 2),
  (2, NULL, 'Fundamentos do judo', 'link', 'https://www.youtube.com/watch?v=5x6O7I0F4NQ', 3),
  (NULL, 3, 'Desenvolvimento de jovens atletas', 'link', 'https://www.youtube.com/watch?v=UBMk30rjy0o', 1);

INSERT INTO comunicados (titulo, mensagem, enviado_por, destinatario_tipo, destinatario_id, criado_em) VALUES
  ('Boas-vindas ao semestre', 'Desejamos um excelente semestre de atividades a todos os alunos e professores.', 1, 'todos', NULL, DATE_SUB(NOW(), INTERVAL 20 DAY)),
  ('Encontro do nucleo', 'Neste sabado teremos atividade especial com alunos e familiares.', 2, 'nucleo', 1, DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO convites (token_hash, tipo, nucleo_id, criado_por, status, expira_em, criado_em) VALUES
  ('c6a4ed29e0387fa47e778cc45625a69a1d975d9d783871b2792c77a812a2df32', 'professor', 6, 1, 'pendente', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()),
  ('f4facfd5607ec8a538bf44cf1ee5f47b5c663a7dc52cc094304d9e35f41afdab', 'aluno', 1, 2, 'pendente', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()),
  ('5e0a6135338b5446c0f4582abc0027a104f27b0b5cc4e0c655c39fc7a9968898', 'aluno', 2, 3, 'expirado', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO audit_log (usuario_id, acao, tabela_afetada, registro_id, ip, user_agent, criado_em) VALUES
  (1, 'login', 'usuarios', 1, '127.0.0.1', 'Demo seed', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (2, 'cadastro', 'chamadas', 36, '127.0.0.1', 'Demo seed', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO notificacoes_log (tipo, descricao, enviado_para, status, criado_em) VALUES
  ('novo-professor', 'Professor demo cadastrado', 'admin@demo.com', 'enviado', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  ('alerta-inativos', 'Resumo demonstrativo de atividade', 'admin@demo.com', 'enviado', DATE_SUB(NOW(), INTERVAL 1 DAY));
