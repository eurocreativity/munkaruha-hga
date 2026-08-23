-- HGA Biomed Munkaruha és Mosodai Nyilvántartó Rendszer
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `short_name` VARCHAR(100) NULL,
  `address` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('admin', 'operator', 'viewer') NOT NULL DEFAULT 'operator',
  `default_location_id` INT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`default_location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `first_name` VARCHAR(100) NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `location_id` INT NULL,
  `is_reserve` TINYINT(1) DEFAULT 0,
  `locker_number` VARCHAR(50) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clothes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barcode` VARCHAR(100) NOT NULL UNIQUE,
  `item_code` VARCHAR(100) NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'Egyéb',
  `color` VARCHAR(50) DEFAULT 'Egyéb',
  `size` VARCHAR(50) NULL,
  `employee_id` INT NULL,
  `location_id` INT NULL,
  `status` ENUM('ACTIVE', 'IN_LAUNDRY', 'RESERVE', 'SCRAPPED', 'LOST') DEFAULT 'ACTIVE',
  `variant` VARCHAR(50) NULL,
  `logo` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `net_value` DECIMAL(12, 2) DEFAULT 0,
  `last_sent_to_laundry` DATETIME NULL,
  `last_received_from_laundry` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_barcode` (`barcode`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_number` VARCHAR(100) NOT NULL UNIQUE,
  `direction` ENUM('OUT', 'IN') NOT NULL,
  `location_id` INT NULL,
  `user_id` INT NULL,
  `status` ENUM('IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'COMPLETED',
  `notes` TEXT NULL,
  `item_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_id` INT NOT NULL,
  `cloth_id` INT NOT NULL,
  `barcode` VARCHAR(100) NOT NULL,
  `direction` ENUM('OUT', 'IN') NOT NULL,
  `location_id` INT NULL,
  `user_id` INT NULL,
  `scanned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  FOREIGN KEY (`batch_id`) REFERENCES `laundry_batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cloth_id`) REFERENCES `clothes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `username` VARCHAR(100) NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(100) NULL,
  `details` TEXT NULL,
  `location_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `locations` (`id`, `code`, `name`, `short_name`, `address`) VALUES
(1, '1', 'HGA Biomed, Kap, Jutai 50.', 'Jutai út 50.', '7400 Kaposvár, Jutai út 50.'),
(2, '2', 'HGA Biomed, Kap, Nagygát utca 1', 'Nagygát u. 1.', '7400 Kaposvár, Nagygát utca 1.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `default_location_id`) VALUES
(1, 'admin', '$2y$10$wO3oW15FspzYf.1iZlO.EOMjOqK87T2B9iG47Dk7h7d2v2uD0f5.2', 'Rendszergazda', 'admin', 1),
(2, 'jutai_operator', '$2y$10$tZ92o4lR7vj7p1q7lU379.7Y67bXv936kXwQ22jG54rG7.qK0m5.6', 'Jutai úti Raktáros', 'operator', 1),
(3, 'nagygat_operator', '$2y$10$vN92o4lR7vj7p1q7lU379.7Y67bXv936kXwQ22jG54rG7.qK0m5.7', 'Nagygát úti Raktáros', 'operator', 2),
(4, 'vezeto', '$2y$10$xP92o4lR7vj7p1q7lU379.7Y67bXv936kXwQ22jG54rG7.qK0m5.8', 'Hanna (Vezető)', 'viewer', 1)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'HGA Biomed Kft.'),
('github_repo', 'eurocreativity/munkaruha-hga'),
('github_token', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Kezdő Dolgozók Leltára
INSERT INTO `employees` (`id`, `employee_code`, `last_name`, `first_name`, `full_name`, `location_id`, `is_reserve`) VALUES
(1, '0002', 'Bencze', 'Miklós', 'Bencze Miklós', 1, 0),
(2, '0096', 'Bogdán-Balogh', 'Tünde', 'Bogdán-Balogh Tünde', 1, 0),
(3, '0092', 'Budai', 'Lívia', 'Budai Lívia', 1, 0),
(4, '0099', 'Czebei', 'Lajos', 'Czebei Lajos', 1, 0),
(5, '0007', 'Dr Ferletyák', 'Marcell', 'Dr Ferletyák Marcell', 1, 0),
(6, '0008', 'Eötvös László', 'Sándor', 'Eötvös László Sándor', 1, 0),
(7, '0087', 'Farkas', 'Zoltán', 'Farkas Zoltán', 1, 0),
(8, '0052', 'Farkas', 'Tibor', 'Farkas Tibor', 1, 0),
(9, '0045', 'Farkasné Lévai', 'Mónika', 'Farkasné Lévai Mónika', 2, 0),
(10, '0057', 'Ferletyák', 'Marcell', 'Ferletyák Marcell', 1, 0),
(11, '0010', 'Gelencsér', 'Ferencné', 'Gelencsér Ferencné', 1, 0),
(12, '0011', 'Gulyás', 'Mónika', 'Gulyás Mónika', 1, 0),
(13, '0022', 'Lukács', 'Róbert', 'Lukács Róbert', 1, 0),
(14, '0016', 'Németh-Kovács', 'Zsanett', 'Németh-Kovács Zsanett', 1, 0),
(15, '0025', 'Páhy Szilvia', 'Ilona', 'Páhy Szilvia Ilona', 1, 0),
(16, '0106', 'Pál', 'Péter', 'Pál Péter', 1, 0),
(17, '0051', 'Papvölgyi', 'József', 'Papvölgyi József', 1, 0),
(18, '0061', 'Reider', 'Balázs', 'Reider Balázs', 1, 0),
(19, '0062', 'Sinka', 'László', 'Sinka László', 1, 0),
(20, '0088', 'Szalkai', 'Klaudia', 'Szalkai Klaudia', 1, 0),
(21, '0043', 'Szemesi', 'János', 'Szemesi János', 2, 0),
(22, '0030', 'Szerencsés', 'Józsefné', 'Szerencsés Józsefné', 1, 0),
(23, '0082', 'Tartalék', '', 'Tartalék (Jutai út)', 1, 1),
(24, '0083', 'Tartalék', '', 'Tartalék (Nagygát u.)', 2, 1),
(25, '0076', 'Tóth', 'Józsefné', 'Tóth Józsefné', 2, 0),
(26, '0053', 'Vörös', 'Patrik', 'Vörös Patrik', 1, 0)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- Kezdő Munkaruhák Leltára
INSERT INTO `clothes` (`barcode`, `item_code`, `name`, `category`, `color`, `size`, `employee_id`, `location_id`, `status`, `variant`, `logo`, `notes`, `net_value`) VALUES
('2077946953', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'XX', 1, 1, 'ACTIVE', '-', '', '', 1452.0),
('1389138421', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'XX', 1, 1, 'ACTIVE', '-', '', '', 1452.0),
('1388826947', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XX', 1, 1, 'ACTIVE', '-', '', '', 1452.0),
('2020819006', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XX', 1, 1, 'ACTIVE', '-', '', '', 1452.0),
('2080235648', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '56/110', 1, 1, 'ACTIVE', 'A', '', '', 4278.0),
('1355635114', '02F6', 'Női Köp.zöld SM-COLOR,A,KÜ', 'Köpeny', 'Zöld', '44/110', 2, 1, 'ACTIVE', '-', '1/1', '', 6537.0),
('2053254317', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XL', 2, 1, 'ACTIVE', '-', '', '', 1763.0),
('2037502953', 'W2S1', 'Női Köp.WhiteLine oldalzs.A', 'Köpeny', 'Fehér', '44/085', 3, 1, 'ACTIVE', 'B', '1/1', '', 4645.0),
('2095038913', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 4, 1, 'ACTIVE', '-', '', '', 1763.0),
('2094990519', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 4, 1, 'ACTIVE', '-', '', '', 1763.0),
('2095339881', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 4, 1, 'ACTIVE', '-', '', '', 1763.0),
('2099335759', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '52/105', 4, 1, 'ACTIVE', 'A', '', '', 5194.0),
('2099335025', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '52/105', 4, 1, 'ACTIVE', 'A', '', '', 5194.0),
('2057951823', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '52/105', 4, 1, 'ACTIVE', 'A', '', '', 5194.0),
('2035274012', '04F6', 'Der.nadr.zöld SM-COLOR,A,KÜ', 'Nadrág', 'Zöld', '52/105', 4, 1, 'ACTIVE', '-', '', '', 5435.0),
('2021271797', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 5, 1, 'ACTIVE', '-', '', '', 830.0),
('1378036226', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'S', 6, 1, 'ACTIVE', '-', '', '', 830.0),
('2053343370', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'XL', 7, 1, 'ACTIVE', '-', '', '', 1452.0),
('1355637156', '01F6', 'Ffi köpeny zöld SM-C B1, KÜ', 'Köpeny', 'Zöld', '52/110', 7, 1, 'ACTIVE', '-', '1/1', '', 5141.0),
('1388889119', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XL', 7, 1, 'ACTIVE', '-', '', '', 1452.0),
('2053155867', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XL', 7, 1, 'ACTIVE', '-', '', '', 1452.0),
('1388889539', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XL', 7, 1, 'ACTIVE', '-', '', '', 1452.0),
('2053422310', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'XL', 7, 1, 'ACTIVE', '-', '', '', 1452.0),
('1378037483', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'M', 8, 1, 'ACTIVE', '-', '', '', 830.0),
('2014731345', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'M', 8, 1, 'ACTIVE', '-', '', '', 830.0),
('1389562974', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'S', 9, 2, 'ACTIVE', '-', '', '', 830.0),
('1389481824', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'S', 9, 2, 'ACTIVE', '-', '', '', 830.0),
('2008069379', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'M', 9, 2, 'ACTIVE', 'B', '1/1', '', 2401.0),
('2008810001', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'S', 10, 1, 'ACTIVE', 'B', '1/1', '', 2401.0),
('1368581484', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', '5X', 11, 1, 'ACTIVE', '-', '', '', 830.0),
('2001513305', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'S', 12, 1, 'ACTIVE', '-', '', '', 830.0),
('2001610981', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'S', 12, 1, 'ACTIVE', '-', '', '', 830.0),
('2014995129', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 13, 1, 'ACTIVE', '-', '', '', 830.0),
('2014822289', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 13, 1, 'ACTIVE', '-', '', '', 830.0),
('1355395582', '04F6', 'Der.nadr.zöld SM-COLOR,A,KÜ', 'Nadrág', 'Zöld', '44/095', 13, 1, 'ACTIVE', '-', '', '', 2558.0),
('2021191385', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '3X', 14, 1, 'ACTIVE', '-', '', 'ez helyett csere polót kapott', 830.0),
('2008442691', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'S', 15, 1, 'ACTIVE', 'B', '1/1', '', 2401.0),
('2008808916', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'S', 15, 1, 'ACTIVE', 'B', '1/1', '', 2401.0),
('2001848315', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'S', 15, 1, 'ACTIVE', '-', '', '', 830.0),
('1378039975', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'S', 15, 1, 'ACTIVE', '-', '', '', 830.0),
('2007774731', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '40/110', 15, 1, 'ACTIVE', '-', '', '', 2304.0),
('2007774472', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '40/110', 15, 1, 'ACTIVE', '-', '', '', 2304.0),
('2085891436', '04F6', 'Der.nadr.zöld SM-COLOR,A,KÜ', 'Nadrág', 'Zöld', '52/105', 16, 1, 'ACTIVE', '-', '', '', 5435.0),
('2014824894', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 17, 1, 'LOST', '-', '', 'kb 5 éve nincs meg', 830.0),
('1375693866', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'M', 18, 1, 'ACTIVE', 'B', '1/1', '', 2401.0),
('1390267288', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('2001604447', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('1389001473', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('1390267639', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('1390335475', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('1389002128', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('2001601286', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 18, 1, 'ACTIVE', '-', '', '', 830.0),
('2021067819', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'XL', 19, 1, 'ACTIVE', '-', '', '', 830.0),
('1356813610', '15F6', 'Női nadr.zöld,SM-COLOR,A,KÜ', 'Nadrág', 'Zöld', '44/110', 20, 1, 'ACTIVE', '-', '', '', 4209.0),
('2030320738', '04F4', 'Der.nadrág,kék SM-COLOR,A,KÜ', 'Nadrág', 'Kék', '48/110', 21, 2, 'LOST', '-', '', 'kb 4 éve el lett küldve, azóta nincs meg', 2558.0),
('1388834072', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 22, 1, 'ACTIVE', '-', '', '', 830.0),
('2021111925', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 22, 1, 'ACTIVE', '-', '', '', 830.0),
('2008805656', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'XS', 23, 1, 'RESERVE', 'B', '1/1', '', 2401.0),
('1388886699', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 23, 1, 'RESERVE', '-', '', '', 830.0),
('2031208806', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '48/100', 23, 1, 'RESERVE', 'A', '', '', 2444.0),
('1361275984', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1389500464', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1371641854', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '56/110', 23, 1, 'RESERVE', '-', '', '', 2304.0),
('1378038886', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('2008143345', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '44/100', 23, 1, 'RESERVE', 'A', '', '', 2444.0),
('1373669566', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1385924745', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '5X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1388917966', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '4X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1389419070', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '3X', 24, 2, 'RESERVE', '-', '', '', 830.0),
('1376204108', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '52/105', 23, 1, 'RESERVE', '-', '', '', 2304.0),
('2036493429', 'W4S1', 'Ff.nadr,WhiteL,zsebes A', 'Nadrág', 'Fehér', '44/100', 23, 1, 'RESERVE', 'A', '', '', 2444.0),
('1386382346', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '52/105', 23, 1, 'RESERVE', '-', '', '', 2304.0),
('1390377512', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('2008288534', 'W3S1', 'Kazak,unisex,fehér,oldalzs.,A', 'Kazak', 'Fehér', 'M', 24, 2, 'RESERVE', 'B', '1/1', '', 2401.0),
('1389108301', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1389107205', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', 'L', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1389416888', 'TSA1', 'Rövid ujjú póló,fehér,KSZ,A', 'Póló', 'Fehér', '3X', 23, 1, 'RESERVE', '-', '', '', 830.0),
('1388285492', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '52/105', 24, 2, 'RESERVE', '-', '', '', 2304.0),
('1375601090', 'W5S1', 'Női nadr.,WhiteLine,oldalzs.A', 'Nadrág', 'Fehér', '56/110', 23, 1, 'RESERVE', '-', '', '', 2304.0),
('1356814013', '15F6', 'Női nadr.zöld,SM-COLOR,A,KÜ', 'Nadrág', 'Zöld', '44/110', 25, 2, 'ACTIVE', '-', '', '', 2405.0),
('2021066164', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 26, 1, 'ACTIVE', '-', '', '', 830.0),
('2021065242', 'TSA4', 'Póló, bottlezöld,A', 'Póló', 'Bottlezöld', 'L', 26, 1, 'ACTIVE', '-', '', '', 830.0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;