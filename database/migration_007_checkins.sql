-- Migration 007: Check-in de geolocalização
-- Adiciona coordenadas nos núcleos + tabela de checkins

ALTER TABLE nucleos
    ADD COLUMN latitude  DECIMAL(10, 8) NULL AFTER estado,
    ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude;

CREATE TABLE IF NOT EXISTS checkins (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    professor_id INT UNSIGNED NOT NULL,
    nucleo_id    INT UNSIGNED NOT NULL,
    latitude     DECIMAL(10, 8) NOT NULL,
    longitude    DECIMAL(11, 8) NOT NULL,
    endereco     VARCHAR(500) NOT NULL DEFAULT '',
    distancia_m  INT UNSIGNED NULL COMMENT 'NULL quando nucleo sem coordenadas cadastradas',
    status       ENUM('dentro_raio','fora_raio','sem_coordenadas') NOT NULL DEFAULT 'sem_coordenadas',
    criado_em    DATETIME NOT NULL,
    INDEX idx_professor_id (professor_id),
    INDEX idx_nucleo_id    (nucleo_id),
    INDEX idx_criado_em    (criado_em),
    INDEX idx_status       (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
