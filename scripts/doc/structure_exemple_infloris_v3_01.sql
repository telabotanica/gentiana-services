CREATE TABLE `chorologie_v3_01` (
  `code_insee` int(11) DEFAULT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `code_ciff` int(11) DEFAULT NULL,
  `num_nom` int(11) DEFAULT NULL,
  `num_tax` int(11) DEFAULT NULL,
  `nom_sci` varchar(255) DEFAULT NULL,
  `presence` int(11) DEFAULT NULL,
  `protection` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ALTER TABLE `chorologie_v3_01` ADD CONSTRAINT cp_code_insee_num_nom PRIMARY KEY(code_insee, num_nom);
CREATE INDEX idx_tax ON chorologie_v3_01(num_tax);
-- CREATE INDEX idx_nom ON chorologie_v3_01(num_nom);

CREATE TABLE `chorologie_nv_v3_01` (
  `num_tax` int(11) DEFAULT NULL,
  `nom_vernaculaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE chorologie_nv_v3_01 ADD CONSTRAINT cp_num_tax_nom_vernaculaire PRIMARY KEY(num_tax, nom_vernaculaire);
CREATE INDEX idx_tax ON chorologie_nv_v3_01(num_tax);
