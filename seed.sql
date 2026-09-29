-- ============================================================================
-- Zion Groups of Companies - seed data (generated from the Stitch mockups)
-- Import:  mysql -u root velora_shop < seed.sql
-- ============================================================================
USE `velora_shop`;
SET NAMES utf8mb4;

-- categories
INSERT INTO `categories` (`id`,`parent_id`,`slug`,`name`,`department`,`description`,`image_url`,`sort_order`) VALUES
(1,NULL,'lingerie','Lingerie','lingerie','Sensual high-fashion intimates, hand-finished in Accra.','https://lh3.googleusercontent.com/aida-public/AB6AXuCuuyCXMxQ6L1Py1XNZIJINPJdIyXsHh7o5WPqMzJasN53SepEyObpSiqpJJdcIcg2koF1CuNrE1hwKZUVipNooIr2FFoWvQ9eGpEIdTwUARm8IUmNK8S1RVNaiULZo4u-xdYDgV38Exe1gr4Z-yJQmTl9xWIDJA5oCSOHwkRfrzwEuX-p_LhYmQCL5VH-UKq6vyGFGNCPEKe6efkdz-2ooQ22vjRx3TFPPUZZaW10N6nFLGmPasp8i',0),
(2,1,'sets-intimates','Sets & Intimates','lingerie','Matching sets, babydolls, bustiers and statement intimates.','https://lh3.googleusercontent.com/aida-public/AB6AXuAc_EklCvGXb9FuBl6wJb63po1kEMpowEcuWgwlSIpSxDJhYIPQkAY4rrqBqBcH0WngJTGQrHwetubgCmZVL8RpfUuWqUAAM_trw6y8P7gi8U4K1kH7dK2nH0Bg3k0eC6g6KnNahGhAYmnxqOpJ3pSvakMJEQ7sEKhzLZ2iMgOYoLZm8hsVEFVTp7htu6IPOI4rajl1xMtCVnwZvZIK561Jvf0TKnhOKUogHHlW4A8HH9YWLJTPQvKr',1),
(3,1,'bras','Bras & Bralettes','lingerie','Balconette, scalloped and wireless silhouettes.','https://lh3.googleusercontent.com/aida-public/AB6AXuDhyr5V3dMCMqO4IrmCt1zmn1WIQzg3ROQV1ZFWMp2ltF_Qj-_O7896HtTBbUsrvjxmqwUjApk4aSPpfDb4m7NZvROH_YOf0jqNX0Pl2yp_tvlw-o9WyhOpWfTF3o4ckSONkMkvwIfE9obmL3vOPxlk796taPUTLVdloqUAbO1XdDEIc5dAJKerSfctGMyy9tdhs19HzVPEDW3nI49t4JaFg-eOhvn5yNDjwkHP1QUs8Ji2TyZoPjNJ',2),
(4,1,'bodysuits','Bodysuits & Shapewear','lingerie','Sculpting lace bodysuits and second-skin fits.','https://lh3.googleusercontent.com/aida-public/AB6AXuCdVPNjbnmW7MjUC6WZzmVbAO0NOefax8rY1OS85wTeJUBesejYH_ZM9n4-_JgFpxu6Q4Iy6HaxmEZIq8lCgwTuxShfI6p43LFfkZZIAvzRgwGyXR5T3n1vXpYqaz3qA3fLc-4d6fYpcRgzQqna5SwYQYETMbYvJ_NVtkbOtcJ46uf7ArGXsE1yJgtude-NfmQhpXv2k_c6sVa33m-8o7daWf-TpB7MSrNgGyL6I0h90KT8-58gMv6J',3),
(5,1,'sleepwear','Sleepwear & Robes','lingerie','Silk chemises, nightdresses and robes.','https://lh3.googleusercontent.com/aida-public/AB6AXuD8N1WCGo3P1UQbJ-zEDSxo_GWI-GO9I0IxnrBDap_thCD8TW2sb_eQgxTdhWJohKI67Be5nbu7gsUuXi-qNQJ7tCS6d5iJrnOfv92Q6N13nZTJVPFGtpZqFe-iWz5YNs3p_B0Wj3dCGzJGvLrvl_Tiug_EaPLl0F_nbxzokmZfKXIIJMSbnF_lCOU0u2TEf6XaMz1b03JPqJLpjhFf4Xd7XrVJ86yj_EjTSpbaUvRhuoFMC0nADMbN',4),
(6,NULL,'instruments','Musical Instruments','instruments','Professional keyboards, guitars, drums and studio audio gear.','https://lh3.googleusercontent.com/aida-public/AB6AXuC3gkXVgVHBIoEanTFNlNwlZ63GIk_rw4w9muAmSICdz66aKzz-8fZvd6ORKlQdcHrCIHcHFUvFoDAKZc4snr5xybo0BSdQQRdkXIoXGyO4CSFJlT8pse4TRp3T9fREG5lAn1ZdMciz7sU7FsMQC4Y53rvsbJbatLKEJPVhItHMw8nM9P5lJ0JymvyjN_L4DzOUrGJkmwLntkbGrWRU27xFIu8S-MAZiUTmLnoTEEjTD4PWVrOGVHFg',5),
(7,6,'keyboards','Keyboards & Synths','instruments','Workstations, synthesizers and stage pianos.','https://lh3.googleusercontent.com/aida-public/AB6AXuATHjn6FY__HoBwQBroxqri5eHWnfN-MYXMMcM4LG6Tc9lDKe5G09WJOn-81a1DUp8ScUKW8Pqx5IDRF27XGnYXc0WVSiZ7zs2pQfrwXRSr93sbtqrbxMJ4YxUO-mEQ_R37uf4POEX6M4bBhJ6BuvIbnlr5ZqJTnM69Y-GMC67kgDooZQiXgeClNmxdTY_V6VCLHu4ImNKGaprBzIhYmCuaJccaSrXA5zDqc0C9T1_8IpOg-wpmuvw1',6),
(8,6,'guitars','Guitars','instruments','Electric, acoustic and bass guitars.','https://lh3.googleusercontent.com/aida-public/AB6AXuC3gkXVgVHBIoEanTFNlNwlZ63GIk_rw4w9muAmSICdz66aKzz-8fZvd6ORKlQdcHrCIHcHFUvFoDAKZc4snr5xybo0BSdQQRdkXIoXGyO4CSFJlT8pse4TRp3T9fREG5lAn1ZdMciz7sU7FsMQC4Y53rvsbJbatLKEJPVhItHMw8nM9P5lJ0JymvyjN_L4DzOUrGJkmwLntkbGrWRU27xFIu8S-MAZiUTmLnoTEEjTD4PWVrOGVHFg',7),
(9,6,'drums','Drums & Percussion','instruments','Acoustic kits, cymbals and electronic percussion.','https://lh3.googleusercontent.com/aida-public/AB6AXuBxsRVc1RqwluNFD9b5z49C_wM7oNTh2qYzGXO7y8PvdAnlGAtq_9bjik8SavgdRQQb1vQrwZGEMkMujYhh0oiN2EtP_YA06s9Q5qKcHEmWX-4cHJOGxSDfCXwq2_h5P4wQbZGBkc6CnzmnqyFEHf_LYcUd0HJg2oClvRvgERjvdMtKUhMHtbq8IoP1ikvxSXGlJGIOxbWU1doMg9JQvqmWMElcirsWufunW__IGVekiH1xe-5nJ-sf',8),
(10,6,'microphones','Microphones','instruments','Dynamic, condenser and broadcast microphones.','https://lh3.googleusercontent.com/aida-public/AB6AXuCTaQgbxncg7mIa5fjRVgmXYVK1e2CXE72OBqW-XHN61Ci-ORAqysZiSr7VqSpLB_Sg-vs-9c4SVokIbSxFDKcSpxvk_4-J8ctgCegQRTnOrXqk2OAlUwb-16nKpgOT_IV-90yg_1nfMZ1lnkQDkqPfgFrfU6f9SMO3fQ9tJYeDdIv1GavPY26rr5tOcyJTvtQiSI0hPb3LBrTa9HYUmCVWsNUM_5iCXKopHzoNYqeO8qAlj9aFoKaZ',9),
(11,6,'audio-gear','Audio & Gear','instruments','Headphones, interfaces, stands and accessories.','https://lh3.googleusercontent.com/aida-public/AB6AXuCOUNXo3ViuAueol4-UxvimfnW8xorboyZTSyt7iZuH_pn2VC7wR-XlsO4btKb0KIMhix_WSBj5prJIP3B8_JHLpfSQDn9N2Hi3ViR2PfLfE4Mt18mrQMsKNw1sMnSgu3jbukGiOamwrgfCbIjyrKu4-FubxkDwqiaa65CUoLOghKqGzv3DcufrWZtMoizIkMmJier_Ey3GAJt6ndvw3BfSHBmRV77n5NwuKG8773VIjM0HZNc49qW_',10);

-- brands
INSERT INTO `brands` (`id`,`slug`,`name`) VALUES
(1,'zion-atelier','Zion Atelier'),
(2,'oh-la-la','Oh La La'),
(3,'zion-essentials','Zion Essentials'),
(4,'zion-couture','Zion Couture'),
(5,'yamaha-pro-sound','Yamaha Pro Sound'),
(6,'roland-corporation','Roland Corporation'),
(7,'yamaha-guitars','Yamaha Guitars'),
(8,'roland-studio','Roland Studio'),
(9,'korg-japan','Korg Japan'),
(10,'clavia-nord','Clavia Nord'),
(11,'zion-sleep','Zion Sleep'),
(12,'zion-private-atelier','Zion Private Atelier'),
(13,'shure-audio-professional','Shure Audio Professional');

-- products
INSERT INTO `products` (`id`,`sku`,`slug`,`category_id`,`brand_id`,`department`,`name`,`brand_label`,`short_description`,`description`,`price`,`compare_at_price`,`stock`,`is_active`,`is_featured`,`badge`,`stock_label`,`rating`,`review_count`,`image_url`) VALUES
(1,'LIN-001','lace-bra-set',2,1,'lingerie','Lace Bra Set','Zion Atelier','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',280.00,NULL,18,1,1,'Best Seller','Accra Stock',4.8,124,'https://lh3.googleusercontent.com/aida-public/AB6AXuAc_EklCvGXb9FuBl6wJb63po1kEMpowEcuWgwlSIpSxDJhYIPQkAY4rrqBqBcH0WngJTGQrHwetubgCmZVL8RpfUuWqUAAM_trw6y8P7gi8U4K1kH7dK2nH0Bg3k0eC6g6KnNahGhAYmnxqOpJ3pSvakMJEQ7sEKhzLZ2iMgOYoLZm8hsVEFVTp7htu6IPOI4rajl1xMtCVnwZvZIK561Jvf0TKnhOKUogHHlW4A8HH9YWLJTPQvKr'),
(2,'LIN-002','satin-balconette-bra',3,1,'lingerie','Satin Balconette Bra','Zion Atelier','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',180.00,NULL,24,1,0,NULL,'Accra Stock',4.6,93,'https://lh3.googleusercontent.com/aida-public/AB6AXuDhyr5V3dMCMqO4IrmCt1zmn1WIQzg3ROQV1ZFWMp2ltF_Qj-_O7896HtTBbUsrvjxmqwUjApk4aSPpfDb4m7NZvROH_YOf0jqNX0Pl2yp_tvlw-o9WyhOpWfTF3o4ckSONkMkvwIfE9obmL3vOPxlk796taPUTLVdloqUAbO1XdDEIc5dAJKerSfctGMyy9tdhs19HzVPEDW3nI49t4JaFg-eOhvn5yNDjwkHP1QUs8Ji2TyZoPjNJ'),
(3,'LIN-003','sculpting-lace-bodysuit',4,2,'lingerie','Sculpting Lace Bodysuit','Oh La La','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',320.00,NULL,11,1,0,NULL,'Accra Stock',4.7,76,'https://lh3.googleusercontent.com/aida-public/AB6AXuCdVPNjbnmW7MjUC6WZzmVbAO0NOefax8rY1OS85wTeJUBesejYH_ZM9n4-_JgFpxu6Q4Iy6HaxmEZIq8lCgwTuxShfI6p43LFfkZZIAvzRgwGyXR5T3n1vXpYqaz3qA3fLc-4d6fYpcRgzQqna5SwYQYETMbYvJ_NVtkbOtcJ46uf7ArGXsE1yJgtude-NfmQhpXv2k_c6sVa33m-8o7daWf-TpB7MSrNgGyL6I0h90KT8-58gMv6J'),
(4,'LIN-004','babydoll-slip-g-string-set',2,1,'lingerie','Babydoll Slip & G-String Set','Zion Atelier','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',250.00,NULL,15,1,0,'New','New This Week',4.6,62,'https://lh3.googleusercontent.com/aida-public/AB6AXuAhXY0akucYuVuH8NiuEa9rUb2_ErvBTwetGtLlcTFRSRmrJFlG-Lrgzvl2JLNUzez4tMlNgPmk5GXp3u4lAtecfPJU9CLpLMVbmXTq2Dki7h1CrXysL8mKbPPKtoWM9oZFUcrNaTCBvfGiIG-cniMi1cUTszg17tvn5Fi_JYUNahOxqd4SJ9e1HDFXlpmOcgq-BEPd9uBSbQtIouMWZNOCgupLPaYmC8PO8c7MxcifzA-BHOHsHKZ6'),
(5,'LIN-005','seamless-thong-3-pack',2,3,'lingerie','Seamless Thong (3-Pack)','Zion Essentials','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',120.00,150.00,32,1,0,'Sale','Accra Stock',4.5,48,'https://lh3.googleusercontent.com/aida-public/AB6AXuC41kZlSwULuxaQgGR0Suc2JYHYXNAWBr6rLbdxnjqCXO0_GTQo2tFOMNdGcmCobBffvCy15-_rHG0bhrA682ZMX7dFXMr25BI5ltNbMn6vL10KPzf793I8RYyFG3sJtPuEw8YXSduBiMIX04R0eltg1hYWMotPiQIATx9qc2h79noWY56EDRWN0uB7ddBz4Bc02lWkwOOT7QaS_i09zWHnASBoc4JgNGr0W_AurBoX_sVBSGAgaX4i'),
(6,'LIN-006','silk-robe-chemise-set',5,1,'lingerie','Silk Robe & Chemise Set','Zion Atelier','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',210.00,NULL,9,1,0,NULL,'Accra Stock',4.7,35,'https://lh3.googleusercontent.com/aida-public/AB6AXuCbJoi-kqkxKx6L-WfpWNDMQ2cpg-2QmZ4sTC4tgSgC-e0-utUuJKNAri2wU_GVdPmwfXWuQ9o8hXhUclJFU3CyybRG3KOzFxq9gf3Rm9u93QQwYkRBHv46Aoxz5ImxvfY2kQqwt-0DT7Zc_PYtNLfKu5h2a2-WMSOzzY4QKptvALniWXHmq3DlEY6LPZlCSjPRrtmAlgxmoS6K_iYRWeEmg9jSj1JLrJQJg2_dTM8jib1UKpDhqqPR'),
(7,'LIN-007','french-scalloped-bralette',3,2,'lingerie','French Scalloped Bralette','Oh La La','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',165.00,NULL,21,1,0,NULL,'Accra Stock',4.9,51,'https://lh3.googleusercontent.com/aida-public/AB6AXuDAjVEqMpgGEoZWmRI8EmrGJSeGTkz4Ly2O1bc7oIYLFF68typPWvR7MRDH10sKec86aHwzYxzLnvC05WEeLm9E2zVxu0XUD07JN3VjKU_eaDGjgInZsD35s0ZoJrcXNpeWxf5TxQfHrCDvlClUCD0SpgXQcw3cuWyrXv19Pxgqq-2519N46O_dObjkJMbuQ76BJm6ix8vMD_lEiEvTFx0X_wmMkLoXE9bzyfo6BBpG_h8ifnsWFYRJ'),
(8,'LIN-008','velvet-touch-bustier',2,4,'lingerie','Velvet Touch Bustier','Zion Couture','Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.','Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton â€” couriers are blind to the contents anywhere in Ghana.',340.00,NULL,4,1,0,'Limited Atelier','Made to Order - 5 Days',5.0,29,'https://lh3.googleusercontent.com/aida-public/AB6AXuCY06yFL_GhreDS5r3NBiWKU5a0854G0BRQRa3skDUgwHxr4d9l6TVU83DhPO4ov4XQV-HWFqfEd2XCLFX40mu9Zw1seVSqKHkfgpPFT1Idu060T7pu4KWfdcifh2kiDsE5wy27uM35snDVlEFD_LwG2LBRmuXRQpLkX7XDtug02kt-Dec_7kMn0MJGaZMu-FMZEFYe1k9uHNWX-9YgjGnHQV3reoYNMkEBQhBXGz_4mnHvf1Isvngo'),
(9,'INS-001','yamaha-psr-sx900-61-key-workstation',7,5,'instruments','Yamaha PSR-SX900 61-Key Workstation','Yamaha Pro Sound','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',3500.00,NULL,6,1,0,'Flagship Arranger','In Stock Accra',4.9,38,'https://lh3.googleusercontent.com/aida-public/AB6AXuD4_QhILewinsMQBX_w8gXCoLUf0g3bYf5gtw98GAI4jfEsloDdKfJVjfOQsnQVrHxaAdhGCu2cpdptbU4mXhgH0oPTMbGjHrP5SgKsyHY-Rfl0NwTitCiR2Wibd2iIbwY6VoySO6QxwUjVBvqF4bODGm3SEzFJBaYv53lcph3mTkZwuzcD_82C-anhzM2mC-yomOq37nbVVjdYPUE6d4gloR4IioNKaXbUOJvEkgeK9EuVU-p9miq7'),
(10,'INS-002','roland-juno-ds-61-synthesizer',7,6,'instruments','Roland Juno-DS 61 Synthesizer','Roland Corporation','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',4200.00,NULL,5,1,0,'Pro Stage Synth','In Stock Accra',4.8,24,'https://lh3.googleusercontent.com/aida-public/AB6AXuBsVOjkuqVgVbaox0fVpzXYolbj3QEv8rPPwyJKeihgDCy5fU7qDoRfULAD8ehDc0chwqsL3VKQgiLAs0lTyZiEgBV2_ExNCj4KQTVYYnrhwZGoZLS9okTWX6Y2G7mFUgAi0gd0wQX59Bd4e2T97l3FWA8MMvaxZ3VuyIg0trXDeOxQ6t-6QJjO-JFIrrKOJbsKAzTSQa4L8v_elWShEP5OQ8TOIdJ9gaU4vTp_t3vKfVE4SWSOpR9F'),
(11,'INS-003','yamaha-pacifica-112v-electric-guitar',8,7,'instruments','Yamaha Pacifica 112V Electric Guitar','Yamaha Guitars','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',4500.00,NULL,8,1,1,'Yamaha Authorized','Showroom Demo Ready',4.7,86,'https://lh3.googleusercontent.com/aida-public/AB6AXuDrRR-VU-QQo29ruvid-Oh3ifYdlo-lVzJ-y5IlTKd31He2LjK5VhxvbdC4x9DvcT1NyD7c6ytF4fpmfmrrUtk8aGdjBZlcDeIZBZpPmxLxwexKvnUCvUiSP5FqTq9WKdb6UUuYPBb99wQYIQqUYS5wju7u3rqQg42-0LN_dvCbPb_sfFK_pHidqC9X4AgYqnO-7ng6agK-NtASYPq30TRjsJYwyC-ewByAEsDeoAnqI2i4Bz8dZvSt'),
(12,'INS-004','roland-fa-08-88-key-music-workstation',7,8,'instruments','Roland FA-08 88-Key Music Workstation','Roland Studio','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',6800.00,NULL,3,1,0,'88 Hammer Weighted','Heavy-Duty Courier Only',5.0,14,'https://lh3.googleusercontent.com/aida-public/AB6AXuDf6lSz7KUosx9WEsuS1n5WHOaXBwQwUZcCF6XVYUswl2404oWfmma8-MsEXKlWlNW0mdyBCRybtQ-sk9hDNidUA9o_QA46u-SrniQFYViyUg6cH8hpmeOqj1WUAzIFKT3F9b8uEt8hutPdpGEUe8gYjG7FreUXWPWgg0R6UUm6-XOhCjZfhY-TADzTfMiQMirYxS1FCjHQwv9sO7Flj2N5HzfekvOe6sqjce7AuLJ0DjTM8JRB5ktv'),
(13,'INS-005','korg-minilogue-xd-polyphonic-synthesizer',7,9,'instruments','Korg Minilogue XD Polyphonic Synthesizer','Korg Japan','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',3100.00,NULL,7,1,0,'Analog Hybrid','In Stock Accra',4.9,42,'https://lh3.googleusercontent.com/aida-public/AB6AXuBEp8CIXp_QmLNel1_5WR98Q56t4L0JVS4RZVCTRv4UWL-9Uqw19F46cjuOt43aS2WeAsO_dCfstFmueGgsto3zs8vxPk9Pgv2IeSwv77pT840JyroEULwSfCVbGl_OILLWs2cB9M5UItVh5jXP24EK-F3YC-3NnZoxoOnIhBHbEubzcXDTDgiZvb5qODO1QuwOXGDEMOTDsAh03qxGAZUDj2717XhxQG1Ud2x1mdvjU7ownw6sOPou'),
(14,'INS-006','nord-stage-3-88-key-stage-keyboard',7,10,'instruments','Nord Stage 3 88-Key Stage Keyboard','Clavia Nord','Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.','Supplied through Zion''s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',14500.00,NULL,2,1,0,'Swedish Mastercraft','Official Importer',5.0,9,'https://lh3.googleusercontent.com/aida-public/AB6AXuDYLHdDFbk17qmLG-mBSgX_NLROgSVrcvw4-d8Hltecl_YKUNaoXsBul5et-aP6PuNel8TaMKWvs2mk6yDtATW5Cqmz3RMsvVZKsrkNI6_X-oNz451eUSNEMmRx6htoDZNUjsXFzFeSIYqRQEycRjV6NKajdks29C3CQ0Fg2G4FDY2BJS-rY6U376aEagNx_Q_5slS1oT7RM-YSnaJWPEKcR-ARpPNsnU_9txaMLeB11jSQAu4UogKQ'),
(15,'LIN-009','lace-balconette-set',3,1,'lingerie','Lace Balconette Set','Zion Atelier','Signature scalloped eyelash lace balconette set with sculpted underwire architecture.','This elegant lace balconette set combines comfort with sophistication. The delicate scalloped lace design and supportive underwire fit make it perfect for everyday luxury or special moments.

- Soft, non-scratch French scalloped eyelash lace cups
- Sculpted underwire architecture for gentle forward lift
- Customizable fit with double-row hook and eye closure
- Includes matching mid-rise French lace bikini brief

Composition: 88% Polyamide, 12% Elastane. Gusset lining: 100% breathable organic cotton. Hand wash lukewarm with gentle silk detergent; do not wring or tumble dry; dry flat away from direct sunlight.',280.00,340.00,14,1,0,'Atelier Exclusive','Ghana In-Stock',5.0,124,'https://lh3.googleusercontent.com/aida-public/AB6AXuDw2f4HjO662tCEKZzPntcwtq8ZvssSoNVmlTlDoVnd5paywLc3onD8h656jlZcyL91E1Cp-jV5AgIgkQp3hqLRvzPHKjvDYxqt7KGig8ULRIZ1o82S8GC6ohDCbLa26SZepGFUB-Qt7ZzwzG-mhaA1NgoT4DhtCHsqYqi5g1dx7YpAgc4nvG8txEuKXJ_z9dJ8X4gUKAueZAcRpz3uNyO62YXEwSrUDCPi4TL8BoIucFyw-ktQU8ND'),
(16,'LIN-010','satin-nightdress',5,11,'lingerie','Satin Nightdress','Zion Sleep','Pure silk-touch satin nightdress with adjustable spaghetti straps.','Rose blush silk satin with a fluid drape and adjustable spaghetti straps. Photographed in a softly lit bedroom setting; designed for warm Accra nights and slow mornings alike.',220.00,NULL,17,1,1,'New Arrival','Pure Silk Touch',4.6,71,'https://lh3.googleusercontent.com/aida-public/AB6AXuD8N1WCGo3P1UQbJ-zEDSxo_GWI-GO9I0IxnrBDap_thCD8TW2sb_eQgxTdhWJohKI67Be5nbu7gsUuXi-qNQJ7tCS6d5iJrnOfv92Q6N13nZTJVPFGtpZqFe-iWz5YNs3p_B0Wj3dCGzJGvLrvl_Tiug_EaPLl0F_nbxzokmZfKXIIJMSbnF_lCOU0u2TEf6XaMz1b03JPqJLpjhFf4Xd7XrVJ86yj_EjTSpbaUvRhuoFMC0nADMbN'),
(17,'LIN-011','lace-bodysuit',4,12,'lingerie','Lace Bodysuit','Zion Private Atelier','Full-coverage sculpting lace bodysuit in Burgundy Noir.','A sculpting lace bodysuit with a smooth, second-skin finish. Shipped in a plain unmarked luxury kraft carton as standard on every Zion intimate order.',250.00,300.00,12,1,0,'Sale','In Stock in Accra',4.7,58,'https://lh3.googleusercontent.com/aida-public/AB6AXuCxrUvbNu4kv9OLedPDDEHoGmEdzjw4B2-cVG4FnjPncU-F8ozGSwhqpwPixdLpDcJMtOU9IKnH4VIbjKBU_QgONVlrn-O6jMmnT4HnWIAHC-IBvxuDfEt0oJ5aYEKOCz_KYuy6DGFQtydCq8u-kWEdE71PWIUW5s8MeimmR0ScT4sqDflgj29OCovnhqC1y90_H7t3MQJ8Pt7wZsqdS_LCP-QpypVlBGr3mnlO7302CDDV2BKfu0as'),
(18,'INS-007','roland-synthesizer',7,8,'instruments','Roland Synthesizer','Roland Studio','61 velocity-sensitive keys with USB-MIDI and a direct-import warranty.','A versatile performance synthesizer for stage and studio, imported directly and covered by Roland regional warranty support.',3000.00,NULL,5,1,1,'Pro Studio','Direct Import',4.9,53,'https://lh3.googleusercontent.com/aida-public/AB6AXuBkAGn0-hSoV3D4Ja7J4NxEgQQeAsY2sXCfddy5wQj3huUXuZsOYyvF2eKoTkHZOfdxxujFu7eAETey5sgEh3XPwowCX9PaKQlolbfJGSAdeY838NCSQa94dkc0PTWG6C0IDYMLoiROEaesyZvLJ6F4YBhV5ddvY1OMTXOR2GTQyRQ0vizFcAOzfMfrE6ZxV_Rrym6dV4jC8qvPkSfEIZbreVV3yzEybypm6fkdrtJUOIfddjVow1tz'),
(19,'INS-008','shure-sm58-dynamic-vocal-microphone',10,13,'instruments','Shure SM58 Dynamic Vocal Microphone','Shure Audio Professional','The industry-standard dynamic vocal microphone, bundled with a 5m XLR cable.','Studio Edition bundle: Shure SM58 plus a high-purity 5m XLR cable. Cardioid dynamic capsule, hardened steel grille, and a lifetime of tour-proven reliability. Express same-day dispatch from Accra.',1200.00,NULL,20,1,0,'Certified Authentic','Express Same-Day Dispatch',4.9,64,'https://lh3.googleusercontent.com/aida-public/AB6AXuCmuMjrmeHwcpmjB7Om9G8MNKG4pKKn4DPTUCtcKgLIYrCBBGfdnoRjWxjo3k6P1MJ5FivyXnxbJHJkoX4ISClqlAl0r71QJ-ZN1FPSACb4e9N9WkWIoBun0pgRej-h6d-9VcJZtUuqp_wQ5LQJEVG1RqDEZhz4-IJEib8qCvt2Nv7OV7veTc4C6McaIOEg8JYE6ng4MMFSbCsTe4L5dtMAaKG8s0zE8qsYX7ON_H5lk09sSCSydZjF');

-- product_images
INSERT INTO `product_images` (`id`,`product_id`,`url`,`alt`,`sort_order`) VALUES
(1,1,'https://lh3.googleusercontent.com/aida-public/AB6AXuAc_EklCvGXb9FuBl6wJb63po1kEMpowEcuWgwlSIpSxDJhYIPQkAY4rrqBqBcH0WngJTGQrHwetubgCmZVL8RpfUuWqUAAM_trw6y8P7gi8U4K1kH7dK2nH0Bg3k0eC6g6KnNahGhAYmnxqOpJ3pSvakMJEQ7sEKhzLZ2iMgOYoLZm8hsVEFVTp7htu6IPOI4rajl1xMtCVnwZvZIK561Jvf0TKnhOKUogHHlW4A8HH9YWLJTPQvKr','Lace Bra Set',1),
(2,2,'https://lh3.googleusercontent.com/aida-public/AB6AXuDhyr5V3dMCMqO4IrmCt1zmn1WIQzg3ROQV1ZFWMp2ltF_Qj-_O7896HtTBbUsrvjxmqwUjApk4aSPpfDb4m7NZvROH_YOf0jqNX0Pl2yp_tvlw-o9WyhOpWfTF3o4ckSONkMkvwIfE9obmL3vOPxlk796taPUTLVdloqUAbO1XdDEIc5dAJKerSfctGMyy9tdhs19HzVPEDW3nI49t4JaFg-eOhvn5yNDjwkHP1QUs8Ji2TyZoPjNJ','Satin Balconette Bra',2),
(3,3,'https://lh3.googleusercontent.com/aida-public/AB6AXuCdVPNjbnmW7MjUC6WZzmVbAO0NOefax8rY1OS85wTeJUBesejYH_ZM9n4-_JgFpxu6Q4Iy6HaxmEZIq8lCgwTuxShfI6p43LFfkZZIAvzRgwGyXR5T3n1vXpYqaz3qA3fLc-4d6fYpcRgzQqna5SwYQYETMbYvJ_NVtkbOtcJ46uf7ArGXsE1yJgtude-NfmQhpXv2k_c6sVa33m-8o7daWf-TpB7MSrNgGyL6I0h90KT8-58gMv6J','Sculpting Lace Bodysuit',3),
(4,4,'https://lh3.googleusercontent.com/aida-public/AB6AXuAhXY0akucYuVuH8NiuEa9rUb2_ErvBTwetGtLlcTFRSRmrJFlG-Lrgzvl2JLNUzez4tMlNgPmk5GXp3u4lAtecfPJU9CLpLMVbmXTq2Dki7h1CrXysL8mKbPPKtoWM9oZFUcrNaTCBvfGiIG-cniMi1cUTszg17tvn5Fi_JYUNahOxqd4SJ9e1HDFXlpmOcgq-BEPd9uBSbQtIouMWZNOCgupLPaYmC8PO8c7MxcifzA-BHOHsHKZ6','Babydoll Slip & G-String Set',4),
(5,5,'https://lh3.googleusercontent.com/aida-public/AB6AXuC41kZlSwULuxaQgGR0Suc2JYHYXNAWBr6rLbdxnjqCXO0_GTQo2tFOMNdGcmCobBffvCy15-_rHG0bhrA682ZMX7dFXMr25BI5ltNbMn6vL10KPzf793I8RYyFG3sJtPuEw8YXSduBiMIX04R0eltg1hYWMotPiQIATx9qc2h79noWY56EDRWN0uB7ddBz4Bc02lWkwOOT7QaS_i09zWHnASBoc4JgNGr0W_AurBoX_sVBSGAgaX4i','Seamless Thong (3-Pack)',5),
(6,6,'https://lh3.googleusercontent.com/aida-public/AB6AXuCbJoi-kqkxKx6L-WfpWNDMQ2cpg-2QmZ4sTC4tgSgC-e0-utUuJKNAri2wU_GVdPmwfXWuQ9o8hXhUclJFU3CyybRG3KOzFxq9gf3Rm9u93QQwYkRBHv46Aoxz5ImxvfY2kQqwt-0DT7Zc_PYtNLfKu5h2a2-WMSOzzY4QKptvALniWXHmq3DlEY6LPZlCSjPRrtmAlgxmoS6K_iYRWeEmg9jSj1JLrJQJg2_dTM8jib1UKpDhqqPR','Silk Robe & Chemise Set',6),
(7,7,'https://lh3.googleusercontent.com/aida-public/AB6AXuDAjVEqMpgGEoZWmRI8EmrGJSeGTkz4Ly2O1bc7oIYLFF68typPWvR7MRDH10sKec86aHwzYxzLnvC05WEeLm9E2zVxu0XUD07JN3VjKU_eaDGjgInZsD35s0ZoJrcXNpeWxf5TxQfHrCDvlClUCD0SpgXQcw3cuWyrXv19Pxgqq-2519N46O_dObjkJMbuQ76BJm6ix8vMD_lEiEvTFx0X_wmMkLoXE9bzyfo6BBpG_h8ifnsWFYRJ','French Scalloped Bralette',7),
(8,8,'https://lh3.googleusercontent.com/aida-public/AB6AXuCY06yFL_GhreDS5r3NBiWKU5a0854G0BRQRa3skDUgwHxr4d9l6TVU83DhPO4ov4XQV-HWFqfEd2XCLFX40mu9Zw1seVSqKHkfgpPFT1Idu060T7pu4KWfdcifh2kiDsE5wy27uM35snDVlEFD_LwG2LBRmuXRQpLkX7XDtug02kt-Dec_7kMn0MJGaZMu-FMZEFYe1k9uHNWX-9YgjGnHQV3reoYNMkEBQhBXGz_4mnHvf1Isvngo','Velvet Touch Bustier',8),
(9,9,'https://lh3.googleusercontent.com/aida-public/AB6AXuD4_QhILewinsMQBX_w8gXCoLUf0g3bYf5gtw98GAI4jfEsloDdKfJVjfOQsnQVrHxaAdhGCu2cpdptbU4mXhgH0oPTMbGjHrP5SgKsyHY-Rfl0NwTitCiR2Wibd2iIbwY6VoySO6QxwUjVBvqF4bODGm3SEzFJBaYv53lcph3mTkZwuzcD_82C-anhzM2mC-yomOq37nbVVjdYPUE6d4gloR4IioNKaXbUOJvEkgeK9EuVU-p9miq7','Yamaha PSR-SX900 61-Key Workstation',9),
(10,10,'https://lh3.googleusercontent.com/aida-public/AB6AXuBsVOjkuqVgVbaox0fVpzXYolbj3QEv8rPPwyJKeihgDCy5fU7qDoRfULAD8ehDc0chwqsL3VKQgiLAs0lTyZiEgBV2_ExNCj4KQTVYYnrhwZGoZLS9okTWX6Y2G7mFUgAi0gd0wQX59Bd4e2T97l3FWA8MMvaxZ3VuyIg0trXDeOxQ6t-6QJjO-JFIrrKOJbsKAzTSQa4L8v_elWShEP5OQ8TOIdJ9gaU4vTp_t3vKfVE4SWSOpR9F','Roland Juno-DS 61 Synthesizer',10),
(11,11,'https://lh3.googleusercontent.com/aida-public/AB6AXuDrRR-VU-QQo29ruvid-Oh3ifYdlo-lVzJ-y5IlTKd31He2LjK5VhxvbdC4x9DvcT1NyD7c6ytF4fpmfmrrUtk8aGdjBZlcDeIZBZpPmxLxwexKvnUCvUiSP5FqTq9WKdb6UUuYPBb99wQYIQqUYS5wju7u3rqQg42-0LN_dvCbPb_sfFK_pHidqC9X4AgYqnO-7ng6agK-NtASYPq30TRjsJYwyC-ewByAEsDeoAnqI2i4Bz8dZvSt','Yamaha Pacifica 112V Electric Guitar',11),
(12,12,'https://lh3.googleusercontent.com/aida-public/AB6AXuDf6lSz7KUosx9WEsuS1n5WHOaXBwQwUZcCF6XVYUswl2404oWfmma8-MsEXKlWlNW0mdyBCRybtQ-sk9hDNidUA9o_QA46u-SrniQFYViyUg6cH8hpmeOqj1WUAzIFKT3F9b8uEt8hutPdpGEUe8gYjG7FreUXWPWgg0R6UUm6-XOhCjZfhY-TADzTfMiQMirYxS1FCjHQwv9sO7Flj2N5HzfekvOe6sqjce7AuLJ0DjTM8JRB5ktv','Roland FA-08 88-Key Music Workstation',12),
(13,13,'https://lh3.googleusercontent.com/aida-public/AB6AXuBEp8CIXp_QmLNel1_5WR98Q56t4L0JVS4RZVCTRv4UWL-9Uqw19F46cjuOt43aS2WeAsO_dCfstFmueGgsto3zs8vxPk9Pgv2IeSwv77pT840JyroEULwSfCVbGl_OILLWs2cB9M5UItVh5jXP24EK-F3YC-3NnZoxoOnIhBHbEubzcXDTDgiZvb5qODO1QuwOXGDEMOTDsAh03qxGAZUDj2717XhxQG1Ud2x1mdvjU7ownw6sOPou','Korg Minilogue XD Polyphonic Synthesizer',13),
(14,14,'https://lh3.googleusercontent.com/aida-public/AB6AXuDYLHdDFbk17qmLG-mBSgX_NLROgSVrcvw4-d8Hltecl_YKUNaoXsBul5et-aP6PuNel8TaMKWvs2mk6yDtATW5Cqmz3RMsvVZKsrkNI6_X-oNz451eUSNEMmRx6htoDZNUjsXFzFeSIYqRQEycRjV6NKajdks29C3CQ0Fg2G4FDY2BJS-rY6U376aEagNx_Q_5slS1oT7RM-YSnaJWPEKcR-ARpPNsnU_9txaMLeB11jSQAu4UogKQ','Nord Stage 3 88-Key Stage Keyboard',14),
(15,15,'https://lh3.googleusercontent.com/aida-public/AB6AXuDw2f4HjO662tCEKZzPntcwtq8ZvssSoNVmlTlDoVnd5paywLc3onD8h656jlZcyL91E1Cp-jV5AgIgkQp3hqLRvzPHKjvDYxqt7KGig8ULRIZ1o82S8GC6ohDCbLa26SZepGFUB-Qt7ZzwzG-mhaA1NgoT4DhtCHsqYqi5g1dx7YpAgc4nvG8txEuKXJ_z9dJ8X4gUKAueZAcRpz3uNyO62YXEwSrUDCPi4TL8BoIucFyw-ktQU8ND','Lace Balconette Set',15),
(16,15,'https://lh3.googleusercontent.com/aida-public/AB6AXuBJ-6S9eetYlzi-AAybU2lP_VnUezg5pao8PF1EVeCqEc8n00ww2LSoKJdDPhiFTmbl1S3zls6AnlX7ZtTYtdKVKEd5MVqnhbBzZvyN9n2kSRnEEdtkozLAZowh9BqAICcFYVFlHfEEJrySlRKSCv8Z9EfFHjklDkAk0WbfuKWTYNBJAt-pWTDi-ZrtNoSCziAmMJspfZnfNkMYnEGIQEW4T8QTGEx3qmWKaIEF8XNiIX9VxD2U4VUt','Lace Balconette Set',16),
(17,15,'https://lh3.googleusercontent.com/aida-public/AB6AXuBp196PkSOatZeIlMzuFBKF2sqxx5tUj66mJmL-XCbIN7XcNTBiWArNmhiLmZTUWawzBPu_IYkYRpbbjhm9o0hDMSnxjXbSXodEOpX02OHZS3ODXKzJNMCur1g-LcEqaBllq3MR87_yzHIpOi59ErUsCRjVC4uCgxkMtwCcS1zOzk4WXGYx-nJ_vkdiS-YgoEt7276F36ZwmZ5622aVTbAEo4cJrWKt7BAOBa-J4YRbwJzFVWKFSw0r','Lace Balconette Set',17),
(18,15,'https://lh3.googleusercontent.com/aida-public/AB6AXuDAy2YQriyP27xIGAPirPh6jVl_3NLrBq2dQoPo1mT4F1s6t8haKEwssvhASFal9Qn_4DlSomamXblxX4bCxpLYQrkJZBlm_VlSnhiwA_ksyYwS7MkNCQmMKnigLIfxHAjm7iifOF3bHOxABMKG9uIFDb1YmDmE7-s10wrW2XmV1osH1sL9gB4X7iQvDS4MqqwyNMTy9Gym54L1NNSLaGeRlcFsfWk1E5s_qBQ9z7c3PGIa1Vky4kE1','Lace Balconette Set',18),
(19,15,'https://lh3.googleusercontent.com/aida-public/AB6AXuBEECKE1rcfLeeJ75qCHP74bGfQB3sHPTZf0DuhUL-Fy9qW55RJjWWQb3D5OBiLCoQfJR-VvJweSftaBebeKNHN6nwVtYoDfqtfBmzPeyu1IMRVd1kUfcFfOKJhRpQMmzWctarHPK3ysq7TWLTGvBpfYRNeelPCzc1PbErLjJMc3zjFiNt3G0h8eJn9MozBtm2PrDCQL1QoRjA-X6h_6Kh94GmOdXkM-6KX1dnfDtVgj-x8nGe1k0Yq','Lace Balconette Set',19),
(20,16,'https://lh3.googleusercontent.com/aida-public/AB6AXuD8N1WCGo3P1UQbJ-zEDSxo_GWI-GO9I0IxnrBDap_thCD8TW2sb_eQgxTdhWJohKI67Be5nbu7gsUuXi-qNQJ7tCS6d5iJrnOfv92Q6N13nZTJVPFGtpZqFe-iWz5YNs3p_B0Wj3dCGzJGvLrvl_Tiug_EaPLl0F_nbxzokmZfKXIIJMSbnF_lCOU0u2TEf6XaMz1b03JPqJLpjhFf4Xd7XrVJ86yj_EjTSpbaUvRhuoFMC0nADMbN','Satin Nightdress',20),
(21,17,'https://lh3.googleusercontent.com/aida-public/AB6AXuCxrUvbNu4kv9OLedPDDEHoGmEdzjw4B2-cVG4FnjPncU-F8ozGSwhqpwPixdLpDcJMtOU9IKnH4VIbjKBU_QgONVlrn-O6jMmnT4HnWIAHC-IBvxuDfEt0oJ5aYEKOCz_KYuy6DGFQtydCq8u-kWEdE71PWIUW5s8MeimmR0ScT4sqDflgj29OCovnhqC1y90_H7t3MQJ8Pt7wZsqdS_LCP-QpypVlBGr3mnlO7302CDDV2BKfu0as','Lace Bodysuit',21),
(22,18,'https://lh3.googleusercontent.com/aida-public/AB6AXuBkAGn0-hSoV3D4Ja7J4NxEgQQeAsY2sXCfddy5wQj3huUXuZsOYyvF2eKoTkHZOfdxxujFu7eAETey5sgEh3XPwowCX9PaKQlolbfJGSAdeY838NCSQa94dkc0PTWG6C0IDYMLoiROEaesyZvLJ6F4YBhV5ddvY1OMTXOR2GTQyRQ0vizFcAOzfMfrE6ZxV_Rrym6dV4jC8qvPkSfEIZbreVV3yzEybypm6fkdrtJUOIfddjVow1tz','Roland Synthesizer',22),
(23,19,'https://lh3.googleusercontent.com/aida-public/AB6AXuCmuMjrmeHwcpmjB7Om9G8MNKG4pKKn4DPTUCtcKgLIYrCBBGfdnoRjWxjo3k6P1MJ5FivyXnxbJHJkoX4ISClqlAl0r71QJ-ZN1FPSACb4e9N9WkWIoBun0pgRej-h6d-9VcJZtUuqp_wQ5LQJEVG1RqDEZhz4-IJEib8qCvt2Nv7OV7veTc4C6McaIOEg8JYE6ng4MMFSbCsTe4L5dtMAaKG8s0zE8qsYX7ON_H5lk09sSCSydZjF','Shure SM58 Dynamic Vocal Microphone',23);

-- product_variants
INSERT INTO `product_variants` (`id`,`product_id`,`type`,`value`,`hex`,`stock`,`sort_order`) VALUES
(1,1,'size','S',NULL,9,0),
(2,1,'size','M',NULL,9,1),
(3,1,'size','L',NULL,9,2),
(4,1,'size','XL',NULL,9,3),
(5,1,'color','Burgundy','#722737',9,0),
(6,1,'color','Noir','#323030',9,1),
(7,1,'color','Rose','#ffd9de',9,2),
(8,2,'size','32B - 38D',NULL,12,0),
(9,2,'color','Champagne','#fed798',12,0),
(10,2,'color','Noir','#323030',12,1),
(11,3,'size','XS',NULL,6,0),
(12,3,'size','XXL',NULL,6,1),
(13,3,'color','Wine','#551022',6,0),
(14,3,'color','Noir','#323030',6,1),
(15,4,'size','S',NULL,8,0),
(16,4,'size','M',NULL,8,1),
(17,4,'size','L',NULL,8,2),
(18,4,'color','Rose Blush','#f9d8dd',8,0),
(19,4,'color','Ivory','#f9f2f1',8,1),
(20,5,'size','XS - XL',NULL,16,0),
(21,5,'color','Noir','#323030',16,0),
(22,5,'color','Rose','#ffd9de',16,1),
(23,5,'color','Ivory','#f9f2f1',16,2),
(24,6,'size','Free Size',NULL,5,0),
(25,6,'color','Champagne','#fed798',5,0),
(26,6,'color','Wine','#551022',5,1),
(27,7,'size','XS',NULL,11,0),
(28,7,'size','S',NULL,11,1),
(29,7,'size','M',NULL,11,2),
(30,7,'size','L',NULL,11,3),
(31,7,'color','Rose Blush','#f9d8dd',11,0),
(32,7,'color','Wine','#551022',11,1),
(33,8,'size','S',NULL,3,0),
(34,8,'size','M',NULL,3,1),
(35,8,'size','L',NULL,3,2),
(36,8,'color','Wine','#551022',3,0),
(37,8,'color','Noir','#323030',3,1),
(38,15,'size','XS',NULL,7,0),
(39,15,'size','S',NULL,7,1),
(40,15,'size','M',NULL,7,2),
(41,15,'size','L',NULL,7,3),
(42,15,'size','XL',NULL,7,4),
(43,15,'size','XXL',NULL,7,5),
(44,15,'color','Burgundy / Wine','#722737',7,0),
(45,15,'color','Obsidian Black','#171515',7,1),
(46,15,'color','Rose Blush','#f9d8dd',7,2),
(47,15,'color','Champagne Nude','#fed798',7,3),
(48,16,'size','XS',NULL,9,0),
(49,16,'size','S',NULL,9,1),
(50,16,'size','M',NULL,9,2),
(51,16,'size','L',NULL,9,3),
(52,16,'color','Rose Blush','#e8a3af',9,0),
(53,16,'color','Ivory','#f4ebe1',9,1),
(54,16,'color','Noir','#202020',9,2),
(55,17,'size','S',NULL,6,0),
(56,17,'size','M',NULL,6,1),
(57,17,'size','L',NULL,6,2),
(58,17,'size','XL',NULL,6,3),
(59,17,'color','Burgundy Noir','#551022',6,0);

-- product_specs
INSERT INTO `product_specs` (`id`,`product_id`,`label`,`value`,`sort_order`) VALUES
(1,9,'6','1',0),
(2,9,'1',',',1),
(3,9,'7','"',2),
(4,9,'C','h',3),
(5,10,'6','1',0),
(6,10,'U','S',1),
(7,10,'B','a',2),
(8,10,'M','i',3),
(9,11,'S','o',0),
(10,11,'A','l',1),
(11,11,'V','i',2),
(12,11,'R','o',3),
(13,12,'8','8',0),
(14,12,'1','6',1),
(15,12,'S','u',2),
(16,12,'S','a',3),
(17,13,'4','-',0),
(18,13,'M','u',1),
(19,13,'1','6',2),
(20,13,'S','t',3),
(21,14,'8','8',0),
(22,14,'D','u',1),
(23,14,'N','o',2),
(24,14,'2','G',3),
(25,15,'Lace Composition','88% Polyamide, 12% Elastane',0),
(26,15,'Gusset Lining','100% Breathable Organic Cotton',1),
(27,15,'Closure','Double-row hook and eye',2),
(28,15,'Brief','Matching mid-rise French lace bikini',3),
(29,18,'Keys','61 Velocity Keys',0),
(30,18,'Connectivity','USB-MIDI',1),
(31,18,'Warranty','2-Year Regional',2),
(32,19,'Type','Dynamic Cardioid',0),
(33,19,'Bundle','Studio Edition',1),
(34,19,'Cable','5m High-Purity XLR',2);

-- product_bundles
INSERT INTO `product_bundles` (`id`,`product_id`,`item_name`,`item_desc`,`item_price`,`sort_order`) VALUES
(1,9,'Yamaha Heavy-Duty Stand','Double-X reinforced',350.00,1),
(2,9,'FC4A Piano Sustain Pedal','Realistic continuous feel',220.00,2),
(3,9,'Padded 61-Key Gig Bag','Water-repellent nylon',280.00,3);

-- users (passwords: Admin123! / Customer123!)
INSERT INTO `users` (`id`,`name`,`email`,`phone`,`password_hash`,`role`,`momo_verified`,`is_active`) VALUES
(1,'Zion Admin','admin@ziongroups.com.gh','+233 50 123 4567','$2y$10$FVEa1bxP2W3USSANweXRzesh2D1wM4YFX2nIePkhRJWjBhAtgbEjy','admin',1,1),
(2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','$2y$10$udyLAvaVjkWFHjZ8uwtPbemM7dXvszJqOgFTeOor/3pBIWY.L4fyO','customer',1,1);

-- addresses
INSERT INTO `addresses` (`id`,`user_id`,`recipient`,`phone`,`region`,`city`,`street`,`is_default`) VALUES
(1,2,'Kwame Mensah','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon',1),
(2,2,'Kwame Mensah','+233 24 492 8812','Ashanti Region','Kumasi','12 Ahodwo Road, Nhyiaeso',0);

-- promo codes
INSERT INTO `promo_codes` (`id`,`code`,`description`,`type`,`value`,`is_active`) VALUES
(1,'ZION10','10% welcome privilege for the Zion Club','percent',10.00,1),
(2,'VIP50','GHâ‚µ 50 off orders above GHâ‚µ 1,000','fixed',50.00,1);

-- wishlist
INSERT INTO `wishlist` (`id`,`user_id`,`product_id`) VALUES
(1,2,1),(2,2,3),(3,2,9),(4,2,12),(5,2,15);

-- demo orders
INSERT INTO `orders` (`id`,`order_no`,`user_id`,`customer_name`,`email`,`phone`,`region`,`city`,`address`,`shipping_method`,`shipping_label`,`shipping_fee`,`subtotal`,`discount`,`total`,`payment_channel`,`payment_reference`,`payment_status`,`status`,`notes`,`created_at`) VALUES
(1,'VEL-2026-8942',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon','metro','Accra Express (Same-Day / 24 hrs)',0.00,4980.00,0.00,4980.00,'momo','MTN-MOMO-8812','paid','shipped','Discreet packaging required on all intimate items.',DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2,'VEL-2026-8957',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon','metro','Accra Express (Same-Day / 24 hrs)',0.00,280.00,28.00,252.00,'telecel','TELECEL-4471','paid','delivered',NULL,DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3,'VEL-2026-9011',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Ashanti Region','Kumasi','12 Ahodwo Road, Nhyiaeso','regional','Regional Road (Kumasi / Takoradi)',45.00,3100.00,0.00,3145.00,'cod',NULL,'pending','pending','Leave with front desk if unavailable.',DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `order_items` (`id`,`order_id`,`product_id`,`product_name`,`product_image`,`variant_text`,`unit_price`,`qty`,`line_total`) VALUES
(1,1,9,'Yamaha PSR-SX900 61-Key Workstation','https://lh3.googleusercontent.com/aida-public/AB6AXuD4_QhILewinsMQBX_w8gXCoLUf0g3bYf5gtw98GAI4jfEsloDdKfJVjfOQsnQVrHxaAdhGCu2cpdptbU4mXhgH0oPTMbGjHrP5SgKsyHY-Rfl0NwTitCiR2Wibd2iIbwY6VoySO6QxwUjVBvqF4bODGm3SEzFJBaYv53lcph3mTkZwuzcD_82C-anhzM2mC-yomOq37nbVVjdYPUE6d4gloR4IioNKaXbUOJvEkgeK9EuVU-p9miq7','Standard 61-Key + Power Adapter',3500.00,1,3500.00),
(2,1,19,'Shure SM58 Dynamic Vocal Microphone','https://lh3.googleusercontent.com/aida-public/AB6AXuCmuMjrmeHwcpmjB7Om9G8MNKG4pKKn4DPTUCtcKgLIYrCBBGfdnoRjWxjo3k6P1MJ5FivyXnxbJHJkoX4ISClqlAl0r71QJ-ZN1FPSACb4e9N9WkWIoBun0pgRej-h6d-9VcJZtUuqp_wQ5LQJEVG1RqDEZhz4-IJEib8qCvt2Nv7OV7veTc4C6McaIOEg8JYE6ng4MMFSbCsTe4L5dtMAaKG8s0zE8qsYX7ON_H5lk09sSCSydZjF','Studio Edition',1200.00,1,1200.00),
(3,1,15,'Lace Balconette Set','https://lh3.googleusercontent.com/aida-public/AB6AXuDw2f4HjO662tCEKZzPntcwtq8ZvssSoNVmlTlDoVnd5paywLc3onD8h656jlZcyL91E1Cp-jV5AgIgkQp3hqLRvzPHKjvDYxqt7KGig8ULRIZ1o82S8GC6ohDCbLa26SZepGFUB-Qt7ZzwzG-mhaA1NgoT4DhtCHsqYqi5g1dx7YpAgc4nvG8txEuKXJ_z9dJ8X4gUKAueZAcRpz3uNyO62YXEwSrUDCPi4TL8BoIucFyw-ktQU8ND','Burgundy / Wine - M',280.00,1,280.00),
(4,2,15,'Lace Balconette Set','https://lh3.googleusercontent.com/aida-public/AB6AXuDw2f4HjO662tCEKZzPntcwtq8ZvssSoNVmlTlDoVnd5paywLc3onD8h656jlZcyL91E1Cp-jV5AgIgkQp3hqLRvzPHKjvDYxqt7KGig8ULRIZ1o82S8GC6ohDCbLa26SZepGFUB-Qt7ZzwzG-mhaA1NgoT4DhtCHsqYqi5g1dx7YpAgc4nvG8txEuKXJ_z9dJ8X4gUKAueZAcRpz3uNyO62YXEwSrUDCPi4TL8BoIucFyw-ktQU8ND','Obsidian Black - S',280.00,1,280.00),
(5,3,13,'Korg Minilogue XD Polyphonic Synthesizer','https://lh3.googleusercontent.com/aida-public/AB6AXuBEp8CIXp_QmLNel1_5WR98Q56t4L0JVS4RZVCTRv4UWL-9Uqw19F46cjuOt43aS2WeAsO_dCfstFmueGgsto3zs8vxPk9Pgv2IeSwv77pT840JyroEULwSfCVbGl_OILLWs2cB9M5UItVh5jXP24EK-F3YC-3NnZoxoOnIhBHbEubzcXDTDgiZvb5qODO1QuwOXGDEMOTDsAh03qxGAZUDj2717XhxQG1Ud2x1mdvjU7ownw6sOPou',NULL,3100.00,1,3100.00);

INSERT INTO `order_events` (`order_id`,`status`,`note`,`created_at`) VALUES
(1,'pending','Order received',DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1,'confirmed','Payment confirmed via MTN MoMo',DATE_ADD(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL 2 MINUTE)),
(1,'packing','Quality & discretion check',DATE_ADD(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL 50 MINUTE)),
(1,'shipped','Van Dispatched (East Legon) - Zion Safe-Van #GW-4821-23',DATE_ADD(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL 270 MINUTE)),
(2,'confirmed','Payment confirmed via Telecel Cash',DATE_ADD(DATE_SUB(NOW(), INTERVAL 12 DAY), INTERVAL 2 MINUTE)),
(2,'delivered','Delivered & signed for',DATE_ADD(DATE_SUB(NOW(), INTERVAL 12 DAY), INTERVAL 180 MINUTE)),
(3,'pending','Order received',DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ==========================================================================
-- Product reviews - products.rating / review_count mirror these approved rows
-- ==========================================================================
DELETE FROM `reviews`;

INSERT INTO `reviews` (`product_id`,`user_id`,`reviewer_name`,`rating`,`title`,`body`,`is_approved`,`created_at`) VALUES
(1,NULL,'Yaw Darko',4,'Discreet delivery, lovely finish','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-08-30 23:24:06'),
(1,NULL,'Efua Asante',5,'Exactly as photographed','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-07-24 23:24:19'),
(1,NULL,'Kojo Nkrumah',5,'Soft lace, no irritation','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-06-17 23:24:32'),
(1,NULL,'Akosua Danso',5,'Worth every cedi','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-05-11 23:24:45'),
(1,NULL,'Nana Ofori',5,'Elegant and well finished','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-04-04 23:24:58'),
(1,NULL,'Adjoa Nyarko',5,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-02-26 23:25:11'),
(2,NULL,'Akosua Danso',4,'Exactly as photographed','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-08-19 23:24:13'),
(2,NULL,'Nana Ofori',4,'Soft lace, no irritation','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-07-13 23:24:26'),
(2,NULL,'Adjoa Nyarko',4,'Worth every cedi','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-06-06 23:24:39'),
(2,NULL,'Selorm Agbeko',5,'Elegant and well finished','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-04-30 23:24:52'),
(2,NULL,'Naa Adjeley',5,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-03-24 23:25:05'),
(2,NULL,'Ibrahim Salifu',5,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-02-15 23:25:18'),
(2,NULL,'Mawuli Tetteh',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-01-09 23:25:31'),
(3,NULL,'Selorm Agbeko',4,'Soft lace, no irritation','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-08-08 23:24:20'),
(3,NULL,'Naa Adjeley',4,'Worth every cedi','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-07-02 23:24:33'),
(3,NULL,'Ibrahim Salifu',5,'Elegant and well finished','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-05-26 23:24:46'),
(3,NULL,'Mawuli Tetteh',5,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-04-19 23:24:59'),
(3,NULL,'Zainab Alhassan',5,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-03-13 23:25:12'),
(3,NULL,'Kofi Antwi',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-02-04 23:25:25'),
(3,NULL,'Yaa Serwaa',5,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2025-12-29 23:25:38'),
(3,NULL,'Emmanuel Quaye',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2025-11-22 23:25:51'),
(4,NULL,'Mawuli Tetteh',4,'Worth every cedi','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-07-28 23:24:27'),
(4,NULL,'Zainab Alhassan',4,'Elegant and well finished','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-06-21 23:24:40'),
(4,NULL,'Kofi Antwi',4,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-05-15 23:24:53'),
(4,NULL,'Yaa Serwaa',4,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-04-08 23:25:06'),
(4,NULL,'Emmanuel Quaye',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-03-02 23:25:19'),
(4,NULL,'Gifty Amoah',5,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-01-24 23:25:32'),
(4,NULL,'Patience Agyemang',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2025-12-18 23:25:45'),
(4,NULL,'Daniel Osei',5,'Discreet delivery, lovely finish','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2025-11-11 23:25:58'),
(4,NULL,'Rita Bonsu',5,'Exactly as photographed','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-10-05 23:26:11'),
(5,NULL,'Yaa Serwaa',4,'Elegant and well finished','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-07-17 23:24:34'),
(5,NULL,'Emmanuel Quaye',4,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-06-10 23:24:47'),
(5,NULL,'Gifty Amoah',4,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-05-04 23:25:00'),
(5,NULL,'Patience Agyemang',4,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-03-28 23:25:13'),
(5,NULL,'Daniel Osei',4,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-02-19 23:25:26'),
(5,NULL,'Rita Bonsu',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-01-13 23:25:39'),
(5,NULL,'Michael Ansah',5,'Discreet delivery, lovely finish','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2025-12-07 23:25:52'),
(5,NULL,'Priscilla Nartey',5,'Exactly as photographed','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-10-31 23:26:05'),
(5,NULL,'Josephine Kudjoe',5,'Soft lace, no irritation','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-09-24 23:26:18'),
(5,NULL,'Ama Mensah',5,'Worth every cedi','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-08-18 23:26:31'),
(6,NULL,'Patience Agyemang',4,'Perfect fit after following the chart','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-07-06 23:24:41'),
(6,NULL,'Daniel Osei',5,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-05-30 23:24:54'),
(6,NULL,'Rita Bonsu',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-04-23 23:25:07'),
(6,NULL,'Michael Ansah',5,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-03-17 23:25:20'),
(6,NULL,'Priscilla Nartey',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-02-08 23:25:33'),
(7,NULL,'Michael Ansah',4,'The packaging was completely plain','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2026-06-25 23:24:48'),
(7,NULL,'Priscilla Nartey',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-05-19 23:25:01'),
(7,NULL,'Josephine Kudjoe',5,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-04-12 23:25:14'),
(7,NULL,'Ama Mensah',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-03-06 23:25:27'),
(7,NULL,'Kwesi Boateng',5,'Discreet delivery, lovely finish','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-01-28 23:25:40'),
(7,NULL,'Abena Owusu',5,'Exactly as photographed','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-12-22 23:25:53'),
(8,NULL,'Ama Mensah',5,'Better than the pictures','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2026-06-14 23:24:55'),
(8,NULL,'Kwesi Boateng',5,'Would buy again','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-05-08 23:25:08'),
(8,NULL,'Abena Owusu',5,'Beautifully made, true to size','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-04-01 23:25:21'),
(8,NULL,'Yaw Darko',5,'Discreet delivery, lovely finish','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-02-23 23:25:34'),
(8,NULL,'Efua Asante',5,'Exactly as photographed','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-01-17 23:25:47'),
(8,NULL,'Kojo Nkrumah',5,'Soft lace, no irritation','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-12-11 23:26:00'),
(8,NULL,'Akosua Danso',5,'Worth every cedi','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-11-04 23:26:13'),
(9,2,'Yaw Darko',4,'Superb action and sound','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2026-06-03 23:25:02'),
(9,2,'Efua Asante',5,'A proper workhorse','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2026-04-27 23:25:15'),
(9,NULL,'Kojo Nkrumah',5,'Exactly what the shop described','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2026-03-21 23:25:28'),
(9,NULL,'Akosua Danso',5,'Great price, genuine unit','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2026-02-12 23:25:41'),
(9,NULL,'Nana Ofori',5,'Setup help was appreciated','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-01-06 23:25:54'),
(9,NULL,'Adjoa Nyarko',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2025-11-30 23:26:07'),
(9,NULL,'Selorm Agbeko',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2025-10-24 23:26:20'),
(9,NULL,'Naa Adjeley',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-09-17 23:26:33'),
(10,NULL,'Akosua Danso',4,'A proper workhorse','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2026-05-23 23:25:09'),
(10,NULL,'Nana Ofori',4,'Exactly what the shop described','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2026-04-16 23:25:22'),
(10,NULL,'Adjoa Nyarko',5,'Great price, genuine unit','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2026-03-10 23:25:35'),
(10,NULL,'Selorm Agbeko',5,'Setup help was appreciated','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-02-01 23:25:48'),
(10,NULL,'Naa Adjeley',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2025-12-26 23:26:01'),
(10,NULL,'Ibrahim Salifu',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2025-11-19 23:26:14'),
(10,NULL,'Mawuli Tetteh',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-10-13 23:26:27'),
(10,NULL,'Zainab Alhassan',5,'Superb action and sound','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2025-09-06 23:26:40'),
(10,NULL,'Kofi Antwi',5,'A proper workhorse','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2025-07-31 23:26:53'),
(11,NULL,'Selorm Agbeko',4,'Exactly what the shop described','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2026-05-12 23:25:16'),
(11,NULL,'Naa Adjeley',4,'Great price, genuine unit','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2026-04-05 23:25:29'),
(11,NULL,'Ibrahim Salifu',4,'Setup help was appreciated','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-02-27 23:25:42'),
(11,NULL,'Mawuli Tetteh',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2026-01-21 23:25:55'),
(11,NULL,'Zainab Alhassan',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2025-12-15 23:26:08'),
(11,NULL,'Kofi Antwi',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-11-08 23:26:21'),
(11,NULL,'Yaa Serwaa',5,'Superb action and sound','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2025-10-02 23:26:34'),
(11,NULL,'Emmanuel Quaye',5,'A proper workhorse','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2025-08-26 23:26:47'),
(11,NULL,'Gifty Amoah',5,'Exactly what the shop described','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2025-07-20 23:27:00'),
(11,NULL,'Patience Agyemang',5,'Great price, genuine unit','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2025-06-13 23:27:13'),
(12,NULL,'Mawuli Tetteh',5,'Great price, genuine unit','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2026-05-01 23:25:23'),
(12,NULL,'Zainab Alhassan',5,'Setup help was appreciated','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-03-25 23:25:36'),
(12,NULL,'Kofi Antwi',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2026-02-16 23:25:49'),
(12,NULL,'Yaa Serwaa',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2026-01-10 23:26:02'),
(12,NULL,'Emmanuel Quaye',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-12-04 23:26:15'),
(13,2,'Yaa Serwaa',4,'Setup help was appreciated','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-04-20 23:25:30'),
(13,2,'Emmanuel Quaye',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2026-03-14 23:25:43'),
(13,NULL,'Gifty Amoah',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2026-02-05 23:25:56'),
(13,NULL,'Patience Agyemang',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-12-30 23:26:09'),
(13,NULL,'Daniel Osei',5,'Superb action and sound','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2025-11-23 23:26:22'),
(13,NULL,'Rita Bonsu',5,'A proper workhorse','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2025-10-17 23:26:35'),
(14,NULL,'Patience Agyemang',5,'Solid build, clean output','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2026-04-09 23:25:37'),
(14,NULL,'Daniel Osei',5,'Recommended by the Accra team','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2026-03-03 23:25:50'),
(14,NULL,'Rita Bonsu',5,'Studio ready out of the box','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2026-01-25 23:26:03'),
(14,NULL,'Michael Ansah',5,'Superb action and sound','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2025-12-19 23:26:16'),
(14,NULL,'Priscilla Nartey',5,'A proper workhorse','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2025-11-12 23:26:29'),
(14,NULL,'Josephine Kudjoe',5,'Exactly what the shop described','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2025-10-06 23:26:42'),
(14,NULL,'Ama Mensah',5,'Great price, genuine unit','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2025-08-30 23:26:55'),
(15,2,'Michael Ansah',5,'Elegant and well finished','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2026-03-29 23:25:44'),
(15,2,'Priscilla Nartey',5,'Perfect fit after following the chart','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-02-20 23:25:57'),
(15,NULL,'Josephine Kudjoe',5,'The packaging was completely plain','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-01-14 23:26:10'),
(15,NULL,'Ama Mensah',5,'Better than the pictures','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-12-08 23:26:23'),
(15,NULL,'Kwesi Boateng',5,'Would buy again','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-11-01 23:26:36'),
(15,NULL,'Abena Owusu',5,'Beautifully made, true to size','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-09-25 23:26:49'),
(15,NULL,'Yaw Darko',5,'Discreet delivery, lovely finish','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2025-08-19 23:27:02'),
(15,NULL,'Efua Asante',5,'Exactly as photographed','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2025-07-13 23:27:15'),
(16,NULL,'Ama Mensah',4,'Perfect fit after following the chart','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2026-03-18 23:25:51'),
(16,NULL,'Kwesi Boateng',4,'The packaging was completely plain','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-02-09 23:26:04'),
(16,NULL,'Abena Owusu',4,'Better than the pictures','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-01-03 23:26:17'),
(16,NULL,'Yaw Darko',4,'Would buy again','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-11-27 23:26:30'),
(16,NULL,'Efua Asante',5,'Beautifully made, true to size','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-10-21 23:26:43'),
(16,NULL,'Kojo Nkrumah',5,'Discreet delivery, lovely finish','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2025-09-14 23:26:56'),
(16,NULL,'Akosua Danso',5,'Exactly as photographed','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2025-08-08 23:27:09'),
(16,NULL,'Nana Ofori',5,'Soft lace, no irritation','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2025-07-02 23:27:22'),
(16,NULL,'Adjoa Nyarko',5,'Worth every cedi','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-05-26 23:27:35'),
(17,NULL,'Yaw Darko',4,'The packaging was completely plain','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2026-03-07 23:25:58'),
(17,NULL,'Efua Asante',4,'Better than the pictures','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2026-01-29 23:26:11'),
(17,NULL,'Kojo Nkrumah',4,'Would buy again','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-12-23 23:26:24'),
(17,NULL,'Akosua Danso',5,'Beautifully made, true to size','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-11-16 23:26:37'),
(17,NULL,'Nana Ofori',5,'Discreet delivery, lovely finish','Beautiful piece but I would size down if you are between sizes. Delivery was discreet - the waybill said household goods.',1,'2025-10-10 23:26:50'),
(17,NULL,'Adjoa Nyarko',5,'Exactly as photographed','Fabric feels more expensive than it is. The adjustable straps actually stay put, which is rare at this price.',1,'2025-09-03 23:27:03'),
(17,NULL,'Selorm Agbeko',5,'Soft lace, no irritation','I was nervous ordering intimates online, but the plain outer packaging and unmarked box put me at ease. Very happy.',1,'2025-07-28 23:27:16'),
(17,NULL,'Naa Adjeley',5,'Worth every cedi','The lace is soft and the seams sit flat - nothing digs in. Sizing chart was accurate and it arrived in a plain box, exactly as promised.',1,'2025-06-21 23:27:29'),
(17,NULL,'Ibrahim Salifu',5,'Elegant and well finished','Ordered on Monday, had it by Tuesday in East Legon. The colour matches the photos and the finish feels properly luxury.',1,'2025-05-15 23:27:42'),
(17,NULL,'Mawuli Tetteh',5,'Perfect fit after following the chart','Fit is true to size and the boning holds its shape after washing. My second order from Zion and the quality is consistent.',1,'2025-04-08 23:27:55'),
(18,NULL,'Akosua Danso',5,'A proper workhorse','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2026-02-24 23:26:05'),
(18,NULL,'Nana Ofori',5,'Exactly what the shop described','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-01-18 23:26:18'),
(18,NULL,'Adjoa Nyarko',5,'Great price, genuine unit','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2025-12-12 23:26:31'),
(18,NULL,'Selorm Agbeko',5,'Setup help was appreciated','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2025-11-05 23:26:44'),
(18,NULL,'Naa Adjeley',5,'Solid build, clean output','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-09-29 23:26:57'),
(19,2,'Selorm Agbeko',4,'Exactly what the shop described','Bought after trying it at the showroom. Latency is negligible, build quality is solid and it came with the power supply and manual.',1,'2026-02-13 23:26:12'),
(19,2,'Naa Adjeley',5,'Great price, genuine unit','Delivered to Takoradi in two days, well boxed. Firmware was current and the team walked me through the first setup over the phone.',1,'2026-01-07 23:26:25'),
(19,NULL,'Ibrahim Salifu',5,'Setup help was appreciated','Clean signal, no hum, and the finish is flawless. Feels like an instrument that will last a decade in the studio.',1,'2025-12-01 23:26:38'),
(19,NULL,'Mawuli Tetteh',5,'Solid build, clean output','Genuine unit with full warranty card. The showroom team were honest about what I actually needed rather than upselling.',1,'2025-10-25 23:26:51'),
(19,NULL,'Zainab Alhassan',5,'Recommended by the Accra team','Good weight, tight hardware and the sound is exactly as expected. Arrived with a proper inspection tag.',1,'2025-09-18 23:27:04'),
(19,NULL,'Kofi Antwi',5,'Studio ready out of the box','Packed properly for the trip upcountry. Action on the keys is even, sounds are usable straight away and the connectivity is complete.',1,'2025-08-12 23:27:17');

UPDATE `products` p
JOIN (SELECT product_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS cnt
        FROM `reviews` WHERE is_approved = 1 GROUP BY product_id) r
  ON r.product_id = p.id
SET p.rating = r.avg_rating, p.review_count = r.cnt;
