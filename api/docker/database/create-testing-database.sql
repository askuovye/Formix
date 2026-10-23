-- Banco separado para a suíte de testes, criado na primeira subida do container.
CREATE DATABASE IF NOT EXISTS testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON testing.* TO 'formularios'@'%';
