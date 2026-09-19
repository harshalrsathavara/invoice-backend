-- Invoices with their lines and taxes. Totals stay 0 here and are computed
-- in SQL afterwards, so the arithmetic has exactly one source of truth.

-- RS/25-26/001 · Gayatri Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 1, 'RS/25-26/001', 'Gayatri Engineering', NULL, '2025-10-09', '',
  'amount', 500, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2025-10-09 12:15:00', '2025-10-09 12:15:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Hydraulic Press Work', 15, 1850, 0, '2025-10-09 12:15:00', '2025-10-09 12:15:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 1, 640, 1, '2025-10-09 12:15:00', '2025-10-09 12:15:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 60, 450, 2, '2025-10-09 12:15:00', '2025-10-09 12:15:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2025-10-09 12:15:00', '2025-10-09 12:15:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2025-10-09 12:15:00', '2025-10-09 12:15:00');

-- RS/25-26/002 · Sardar Iron Works
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 2, 'RS/25-26/002', 'Sardar Iron Works', NULL, '2025-10-21', '',
  'none', 0, 0, 'Rework on 4 pieces at no charge.', 0, 0, NULL,
  '', NULL, NULL, '2025-10-21 11:21:00', '2025-10-21 11:21:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Boring Work', 15, 750, 0, '2025-10-21 11:21:00', '2025-10-21 11:21:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2025-10-21 11:21:00', '2025-10-21 11:21:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2025-10-21 11:21:00', '2025-10-21 11:21:00');

-- RS/25-26/003 · Patel Metal Works
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 3, 'RS/25-26/003', 'Patel Metal Works', NULL, '2025-11-03', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2025-11-03 20:35:00', '2025-11-03 20:35:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Milling Job Work', 12, 670, 0, '2025-11-03 20:35:00', '2025-11-03 20:35:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2025-11-03 20:35:00', '2025-11-03 20:35:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2025-11-03 20:35:00', '2025-11-03 20:35:00');

-- RS/25-26/004 · Kiran Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 4, 'RS/25-26/004', 'Kiran Engineering', NULL, '2025-11-16', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2025-11-16 18:29:00', '2025-11-16 18:29:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Milling Job Work', 18, 645, 0, '2025-11-16 18:29:00', '2025-11-16 18:29:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 5, 640, 1, '2025-11-16 18:29:00', '2025-11-16 18:29:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2025-11-16 18:29:00', '2025-11-16 18:29:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2025-11-16 18:29:00', '2025-11-16 18:29:00');

-- RS/25-26/005 · Dhanlaxmi Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 5, 'RS/25-26/005', 'Dhanlaxmi Traders', NULL, '2025-11-28', '',
  'none', 0, 0, '', 0, 0, '2025-11-30 11:00:00',
  'Raised twice by mistake', NULL, NULL, '2025-11-28 15:48:00', '2025-11-28 15:48:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Hydraulic Press Work', 60, 1875, 0, '2025-11-28 15:48:00', '2025-11-28 15:48:00');

-- RS/25-26/006 · Dhanlaxmi Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 6, 'RS/25-26/006', 'Dhanlaxmi Traders', NULL, '2025-12-11', '',
  'none', 0, 0, 'Transport arranged by party.', 0, 0, NULL,
  '', NULL, NULL, '2025-12-11 15:00:00', '2025-12-11 15:00:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Shaping Work', 40, 520, 0, '2025-12-11 15:00:00', '2025-12-11 15:00:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 60, 380, 1, '2025-12-11 15:00:00', '2025-12-11 15:00:00');

-- RS/25-26/007 · Shakti Metal Corporation
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 7, 'RS/25-26/007', 'Shakti Metal Corporation', NULL, '2025-12-23', '',
  'amount', 1500, 0, 'Transport arranged by party.', 0, 0, NULL,
  '', NULL, NULL, '2025-12-23 19:06:00', '2025-12-23 19:06:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Drilling Work', 1, 290, 0, '2025-12-23 19:06:00', '2025-12-23 19:06:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Heat Treatment', 8, 1150, 1, '2025-12-23 19:06:00', '2025-12-23 19:06:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 15, 450, 2, '2025-12-23 19:06:00', '2025-12-23 19:06:00');

-- RS/25-26/008 · Kiran Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 8, 'RS/25-26/008', 'Kiran Engineering', NULL, '2026-01-05', '',
  'percent', 3, 0, 'Urgent delivery, night shift.', 0, 0, NULL,
  '', NULL, NULL, '2026-01-05 15:30:00', '2026-01-05 15:30:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Milling Job Work', 10, 620, 0, '2026-01-05 15:30:00', '2026-01-05 15:30:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Welding', 1, 2000, 1, '2026-01-05 15:30:00', '2026-01-05 15:30:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-01-05 15:30:00', '2026-01-05 15:30:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-01-05 15:30:00', '2026-01-05 15:30:00');

-- RS/25-26/009 · Dhanlaxmi Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 9, 'RS/25-26/009', 'Dhanlaxmi Traders', NULL, '2026-01-17', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-01-17 15:36:00', '2026-01-17 15:36:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 5, 405, 0, '2026-01-17 15:36:00', '2026-01-17 15:36:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-01-17 15:36:00', '2026-01-17 15:36:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-01-17 15:36:00', '2026-01-17 15:36:00');

-- RS/25-26/010 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 10, 'RS/25-26/010', 'Jay Ambe Fabricators', NULL, '2026-01-30', '',
  'none', 0, 0, 'Drawings supplied by customer.', 0, 0, NULL,
  '', NULL, NULL, '2026-01-30 13:18:00', '2026-01-30 13:18:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 1, 500, 0, '2026-01-30 13:18:00', '2026-01-30 13:18:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-01-30 13:18:00', '2026-01-30 13:18:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-01-30 13:18:00', '2026-01-30 13:18:00');

-- RS/25-26/011 · Rameshbhai Patel
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 11, 'RS/25-26/011', 'Rameshbhai Patel', NULL, '2026-02-12', '',
  'none', 0, 0, 'Transport arranged by party.', 0, 0, '2026-02-14 11:00:00',
  'Raised twice by mistake', NULL, NULL, '2026-02-12 10:32:00', '2026-02-12 10:32:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Drilling Work', 20, 315, 0, '2026-02-12 10:32:00', '2026-02-12 10:32:00');

-- RS/25-26/012 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 12, 'RS/25-26/012', 'Jay Ambe Fabricators', NULL, '2026-02-24', '',
  'none', 0, 0, 'Transport arranged by party.', 0, 0, NULL,
  '', NULL, NULL, '2026-02-24 20:52:00', '2026-02-24 20:52:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 4, 360, 0, '2026-02-24 20:52:00', '2026-02-24 20:52:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Shaping Work', 24, 520, 1, '2026-02-24 20:52:00', '2026-02-24 20:52:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-02-24 20:52:00', '2026-02-24 20:52:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-02-24 20:52:00', '2026-02-24 20:52:00');

-- RS/25-26/013 · Dhanlaxmi Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 13, 'RS/25-26/013', 'Dhanlaxmi Traders', NULL, '2026-03-09', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-03-09 20:43:00', '2026-03-09 20:43:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 36, 620, 0, '2026-03-09 20:43:00', '2026-03-09 20:43:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 36, 640, 1, '2026-03-09 20:43:00', '2026-03-09 20:43:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-03-09 20:43:00', '2026-03-09 20:43:00');

-- RS/25-26/014 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 14, 'RS/25-26/014', 'Jay Ambe Fabricators', NULL, '2026-03-21', '',
  'none', 0, 0, 'Material returned with lot.', 0, 0, NULL,
  '', NULL, NULL, '2026-03-21 20:42:00', '2026-03-21 20:42:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Shaping Work', 2, 520, 0, '2026-03-21 20:42:00', '2026-03-21 20:42:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-03-21 20:42:00', '2026-03-21 20:42:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-03-21 20:42:00', '2026-03-21 20:42:00');

-- RS/26-27/020 · Navkar Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 20, 'RS/26-27/020', 'Navkar Industries', NULL, '2026-04-11', '',
  'percent', 2, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-04-11 19:37:00', '2026-04-11 19:37:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Milling Job Work', 8, 620, 0, '2026-04-11 19:37:00', '2026-04-11 19:37:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Welding', 24, 2000, 1, '2026-04-11 19:37:00', '2026-04-11 19:37:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 36, 640, 2, '2026-04-11 19:37:00', '2026-04-11 19:37:00');

-- RS/26-27/021 · Gayatri Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 21, 'RS/26-27/021', 'Gayatri Engineering', NULL, '2026-04-23', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-04-23 16:41:00', '2026-04-23 16:41:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Welding', 60, 2025, 0, '2026-04-23 16:41:00', '2026-04-23 16:41:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-04-23 16:41:00', '2026-04-23 16:41:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-04-23 16:41:00', '2026-04-23 16:41:00');

-- RS/26-27/022 · Kiran Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 22, 'RS/26-27/022', 'Kiran Engineering', NULL, '2026-05-05', '',
  'percent', 10, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-05-05 15:00:00', '2026-05-05 15:00:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 18, 430, 0, '2026-05-05 15:00:00', '2026-05-05 15:00:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-05-05 15:00:00', '2026-05-05 15:00:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-05-05 15:00:00', '2026-05-05 15:00:00');

-- RS/26-27/023 · Rameshbhai Patel
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 23, 'RS/26-27/023', 'Rameshbhai Patel', NULL, '2026-05-17', '',
  'percent', 10, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-05-17 10:04:00', '2026-05-17 10:04:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Heat Treatment', 12, 1130, 0, '2026-05-17 10:04:00', '2026-05-17 10:04:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-05-17 10:04:00', '2026-05-17 10:04:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-05-17 10:04:00', '2026-05-17 10:04:00');

-- RS/26-27/024 · Gayatri Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 24, 'RS/26-27/024', 'Gayatri Engineering', NULL, '2026-05-29', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-05-29 14:30:00', '2026-05-29 14:30:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Hydraulic Press Work', 36, 1900, 0, '2026-05-29 14:30:00', '2026-05-29 14:30:00');

-- RS/26-27/025 · Dhanlaxmi Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 25, 'RS/26-27/025', 'Dhanlaxmi Traders', NULL, '2026-06-10', '',
  'none', 0, 0, 'Drawings supplied by customer.', 0, 0, NULL,
  '', NULL, NULL, '2026-06-10 12:25:00', '2026-06-10 12:25:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 24, 380, 0, '2026-06-10 12:25:00', '2026-06-10 12:25:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Drilling Work', 12, 270, 1, '2026-06-10 12:25:00', '2026-06-10 12:25:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-06-10 12:25:00', '2026-06-10 12:25:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-06-10 12:25:00', '2026-06-10 12:25:00');

-- RS/26-27/026 · Rameshbhai Patel
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 26, 'RS/26-27/026', 'Rameshbhai Patel', NULL, '2026-06-23', '',
  'none', 0, 0, 'Urgent delivery, night shift.', 0, 0, NULL,
  '', NULL, NULL, '2026-06-23 12:16:00', '2026-06-23 12:16:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Welding', 18, 1980, 0, '2026-06-23 12:16:00', '2026-06-23 12:16:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-06-23 12:16:00', '2026-06-23 12:16:00');

-- RS/26-27/027 · Bhavani Auto Parts
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 27, 'RS/26-27/027', 'Bhavani Auto Parts', NULL, '2026-07-05', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-05 12:44:00', '2026-07-05 12:44:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 20, 380, 0, '2026-07-05 12:44:00', '2026-07-05 12:44:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 20, 430, 1, '2026-07-05 12:44:00', '2026-07-05 12:44:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-07-05 12:44:00', '2026-07-05 12:44:00');

-- RS/26-27/028 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 28, 'RS/26-27/028', 'Jay Ambe Fabricators', NULL, '2026-07-17', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-17 18:27:00', '2026-07-17 18:27:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Heat Treatment', 30, 1175, 0, '2026-07-17 18:27:00', '2026-07-17 18:27:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Heat Treatment', 36, 1150, 1, '2026-07-17 18:27:00', '2026-07-17 18:27:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-07-17 18:27:00', '2026-07-17 18:27:00');

-- RS/26-27/029 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 29, 'RS/26-27/029', 'Jay Ambe Fabricators', NULL, '2026-07-29', '',
  'none', 0, 0, 'Drawings supplied by customer.', 0, 0, NULL,
  '', NULL, NULL, '2026-07-29 15:39:00', '2026-07-29 15:39:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 48, 360, 0, '2026-07-29 15:39:00', '2026-07-29 15:39:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-07-29 15:39:00', '2026-07-29 15:39:00');

-- RS/26-27/030 · Mahesh Traders
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 30, 'RS/26-27/030', 'Mahesh Traders', NULL, '2026-08-10', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-10 18:04:00', '2026-08-10 18:04:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 5, 380, 0, '2026-08-10 18:04:00', '2026-08-10 18:04:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-08-10 18:04:00', '2026-08-10 18:04:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-08-10 18:04:00', '2026-08-10 18:04:00');

-- RS/26-27/031 · Bhavani Auto Parts
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 31, 'RS/26-27/031', 'Bhavani Auto Parts', NULL, '2026-08-22', '',
  'amount', 500, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-22 20:58:00', '2026-08-22 20:58:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Shaping Work', 4, 570, 0, '2026-08-22 20:58:00', '2026-08-22 20:58:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-08-22 20:58:00', '2026-08-22 20:58:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-08-22 20:58:00', '2026-08-22 20:58:00');

-- RS/26-27/032 · Shreeji Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'bill', 32, 'RS/26-27/032', 'Shreeji Industries', NULL, '2026-09-03', '',
  'none', 0, 0, 'Drawings supplied by customer.', 0, 0, NULL,
  '', NULL, NULL, '2026-09-03 11:21:00', '2026-09-03 11:21:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Drilling Work', 15, 290, 0, '2026-09-03 11:21:00', '2026-09-03 11:21:00');

-- RS/QT/26-27/002 · Rameshbhai Patel
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'quotation', 2, 'RS/QT/26-27/002', 'Rameshbhai Patel', NULL, '2026-06-17', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-06-17 18:02:00', '2026-06-17 18:02:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Heat Treatment', 5, 1130, 0, '2026-06-17 18:02:00', '2026-06-17 18:02:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Drilling Work', 1, 290, 1, '2026-06-17 18:02:00', '2026-06-17 18:02:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 2, 500, 2, '2026-06-17 18:02:00', '2026-06-17 18:02:00');

-- RS/QT/26-27/003 · Gayatri Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'quotation', 3, 'RS/QT/26-27/003', 'Gayatri Engineering', NULL, '2026-07-20', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-20 16:29:00', '2026-07-20 16:29:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Key Way Cutting', 4, 640, 0, '2026-07-20 16:29:00', '2026-07-20 16:29:00');

-- RS/QT/26-27/004 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'quotation', 4, 'RS/QT/26-27/004', 'Jay Ambe Fabricators', NULL, '2026-08-22', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-22 14:33:00', '2026-08-22 14:33:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Lathe Job Work', 10, 450, 0, '2026-08-22 14:33:00', '2026-08-22 14:33:00');

-- RS/DC/26-27/002 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'challan', 2, 'RS/DC/26-27/002', 'Jay Ambe Fabricators', NULL, '2026-07-18', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-18 12:17:00', '2026-07-18 12:17:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Surface Grinding', 2, 430, 0, '2026-07-18 12:17:00', '2026-07-18 12:17:00');

-- RS/DC/26-27/003 · Jay Ambe Fabricators
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b1, UUID(), 'challan', 3, 'RS/DC/26-27/003', 'Jay Ambe Fabricators', NULL, '2026-08-19', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-19 17:05:00', '2026-08-19 17:05:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Shaping Work', 48, 570, 0, '2026-08-19 17:05:00', '2026-08-19 17:05:00');

-- UE/26-27/001 · Krishna Precision
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 1, 'UE/26-27/001', 'Krishna Precision', NULL, '2026-04-16', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-04-16 10:34:00', '2026-04-16 10:34:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Powder Coating', 48, 760, 0, '2026-04-16 10:34:00', '2026-04-16 10:34:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 10, 410, 1, '2026-04-16 10:34:00', '2026-04-16 10:34:00');

-- UE/26-27/002 · Om Sai Enterprise
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 2, 'UE/26-27/002', 'Om Sai Enterprise', NULL, '2026-04-26', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-04-26 17:20:00', '2026-04-26 17:20:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 24, 1500, 0, '2026-04-26 17:20:00', '2026-04-26 17:20:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-04-26 17:20:00', '2026-04-26 17:20:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-04-26 17:20:00', '2026-04-26 17:20:00');

-- UE/26-27/003 · Suryadeep Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 3, 'UE/26-27/003', 'Suryadeep Industries', NULL, '2026-05-05', '',
  'none', 0, 0, '', 0, 0, '2026-05-07 11:00:00',
  'Raised twice by mistake', NULL, NULL, '2026-05-05 12:49:00', '2026-05-05 12:49:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'CNC Turning', 12, 1030, 0, '2026-05-05 12:49:00', '2026-05-05 12:49:00');

-- UE/26-27/004 · Parshwanath Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 4, 'UE/26-27/004', 'Parshwanath Engineering', NULL, '2026-05-15', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-05-15 12:38:00', '2026-05-15 12:38:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 5, 1370, 0, '2026-05-15 12:38:00', '2026-05-15 12:38:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-05-15 12:38:00', '2026-05-15 12:38:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-05-15 12:38:00', '2026-05-15 12:38:00');

-- UE/26-27/005 · Suryadeep Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 5, 'UE/26-27/005', 'Suryadeep Industries', NULL, '2026-05-25', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-05-25 16:11:00', '2026-05-25 16:11:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 1, 1320, 0, '2026-05-25 16:11:00', '2026-05-25 16:11:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-05-25 16:11:00', '2026-05-25 16:11:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-05-25 16:11:00', '2026-05-25 16:11:00');

-- UE/26-27/006 · Om Sai Enterprise
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 6, 'UE/26-27/006', 'Om Sai Enterprise', NULL, '2026-06-03', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-06-03 16:16:00', '2026-06-03 16:16:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 5, 2230, 0, '2026-06-03 16:16:00', '2026-06-03 16:16:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 2, 2250, 1, '2026-06-03 16:16:00', '2026-06-03 16:16:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-06-03 16:16:00', '2026-06-03 16:16:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-06-03 16:16:00', '2026-06-03 16:16:00');

-- UE/26-27/007 · Tirupati Engineers
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 7, 'UE/26-27/007', 'Tirupati Engineers', NULL, '2026-06-13', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-06-13 11:06:00', '2026-06-13 11:06:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 5, 460, 0, '2026-06-13 11:06:00', '2026-06-13 11:06:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 18, 410, 1, '2026-06-13 11:06:00', '2026-06-13 11:06:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 20, 1320, 2, '2026-06-13 11:06:00', '2026-06-13 11:06:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-06-13 11:06:00', '2026-06-13 11:06:00');

-- UE/26-27/008 · Om Sai Enterprise
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 8, 'UE/26-27/008', 'Om Sai Enterprise', NULL, '2026-06-22', '',
  'amount', 500, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-06-22 20:21:00', '2026-06-22 20:21:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 10, 2300, 0, '2026-06-22 20:21:00', '2026-06-22 20:21:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 2, 1550, 1, '2026-06-22 20:21:00', '2026-06-22 20:21:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'CNC Turning', 40, 1030, 2, '2026-06-22 20:21:00', '2026-06-22 20:21:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-06-22 20:21:00', '2026-06-22 20:21:00');

-- UE/26-27/009 · Parshwanath Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 9, 'UE/26-27/009', 'Parshwanath Engineering', NULL, '2026-07-02', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-02 11:07:00', '2026-07-02 11:07:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 12, 460, 0, '2026-07-02 11:07:00', '2026-07-02 11:07:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Powder Coating', 48, 760, 1, '2026-07-02 11:07:00', '2026-07-02 11:07:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-07-02 11:07:00', '2026-07-02 11:07:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-07-02 11:07:00', '2026-07-02 11:07:00');

-- UE/26-27/010 · Parshwanath Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 10, 'UE/26-27/010', 'Parshwanath Engineering', NULL, '2026-07-11', '',
  'none', 0, 0, 'Drawings supplied by customer.', 0, 0, NULL,
  '', NULL, NULL, '2026-07-11 10:08:00', '2026-07-11 10:08:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 20, 1345, 0, '2026-07-11 10:08:00', '2026-07-11 10:08:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-07-11 10:08:00', '2026-07-11 10:08:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-07-11 10:08:00', '2026-07-11 10:08:00');

-- UE/26-27/011 · Suryadeep Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 11, 'UE/26-27/011', 'Suryadeep Industries', NULL, '2026-07-21', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-21 11:15:00', '2026-07-21 11:15:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'CNC Turning', 24, 960, 0, '2026-07-21 11:15:00', '2026-07-21 11:15:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 15, 1550, 1, '2026-07-21 11:15:00', '2026-07-21 11:15:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-07-21 11:15:00', '2026-07-21 11:15:00');

-- UE/26-27/012 · Mehul Fabrication
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 12, 'UE/26-27/012', 'Mehul Fabrication', NULL, '2026-07-30', '',
  'amount', 500, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-30 15:01:00', '2026-07-30 15:01:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 5, 2250, 0, '2026-07-30 15:01:00', '2026-07-30 15:01:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-07-30 15:01:00', '2026-07-30 15:01:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-07-30 15:01:00', '2026-07-30 15:01:00');

-- UE/26-27/013 · Tirupati Engineers
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 13, 'UE/26-27/013', 'Tirupati Engineers', NULL, '2026-08-09', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-09 18:51:00', '2026-08-09 18:51:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Powder Coating', 8, 760, 0, '2026-08-09 18:51:00', '2026-08-09 18:51:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-08-09 18:51:00', '2026-08-09 18:51:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-08-09 18:51:00', '2026-08-09 18:51:00');

-- UE/26-27/014 · Krishna Precision
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 14, 'UE/26-27/014', 'Krishna Precision', NULL, '2026-08-19', '',
  'percent', 2, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-19 10:34:00', '2026-08-19 10:34:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 36, 410, 0, '2026-08-19 10:34:00', '2026-08-19 10:34:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 15, 1370, 1, '2026-08-19 10:34:00', '2026-08-19 10:34:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'IGST', 18.0, '2026-08-19 10:34:00', '2026-08-19 10:34:00');

-- UE/26-27/015 · Parshwanath Engineering
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 15, 'UE/26-27/015', 'Parshwanath Engineering', NULL, '2026-08-28', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-28 16:26:00', '2026-08-28 16:26:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 2, 1500, 0, '2026-08-28 16:26:00', '2026-08-28 16:26:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Laser Cutting', 20, 1320, 1, '2026-08-28 16:26:00', '2026-08-28 16:26:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 2.5, '2026-08-28 16:26:00', '2026-08-28 16:26:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 2.5, '2026-08-28 16:26:00', '2026-08-28 16:26:00');

-- UE/26-27/016 · Arihant Steel Centre
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'bill', 16, 'UE/26-27/016', 'Arihant Steel Centre', NULL, '2026-09-07', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-09-07 13:52:00', '2026-09-07 13:52:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 24, 2250, 0, '2026-09-07 13:52:00', '2026-09-07 13:52:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'CGST', 9.0, '2026-09-07 13:52:00', '2026-09-07 13:52:00');
INSERT INTO invoice_taxes (invoice_id, uuid, label, percent, created_at, updated_at) VALUES (@inv, UUID(), 'SGST', 9.0, '2026-09-07 13:52:00', '2026-09-07 13:52:00');

-- UE/QT/26-27/001 · Om Sai Enterprise
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'quotation', 1, 'UE/QT/26-27/001', 'Om Sai Enterprise', NULL, '2026-06-07', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-06-07 15:20:00', '2026-06-07 15:20:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 20, 410, 0, '2026-06-07 15:20:00', '2026-06-07 15:20:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Sheet Bending', 18, 410, 1, '2026-06-07 15:20:00', '2026-06-07 15:20:00');

-- UE/QT/26-27/002 · Arihant Steel Centre
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'quotation', 2, 'UE/QT/26-27/002', 'Arihant Steel Centre', NULL, '2026-07-15', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-15 12:59:00', '2026-07-15 12:59:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'CNC Turning', 60, 960, 0, '2026-07-15 12:59:00', '2026-07-15 12:59:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 5, 2300, 1, '2026-07-15 12:59:00', '2026-07-15 12:59:00');

-- UE/QT/26-27/003 · Tirupati Engineers
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'quotation', 3, 'UE/QT/26-27/003', 'Tirupati Engineers', NULL, '2026-08-21', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-21 14:55:00', '2026-08-21 14:55:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 2, 1550, 0, '2026-08-21 14:55:00', '2026-08-21 14:55:00');

-- UE/DC/26-27/001 · Suryadeep Industries
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'challan', 1, 'UE/DC/26-27/001', 'Suryadeep Industries', NULL, '2026-07-05', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-07-05 15:53:00', '2026-07-05 15:53:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 10, 1480, 0, '2026-07-05 15:53:00', '2026-07-05 15:53:00');

-- UE/DC/26-27/002 · Krishna Precision
INSERT INTO invoices
 (business_id, uuid, doc_type, bill_no, bill_ref, customer_name, customer_uuid, date, amount_in_words,
  discount_type, discount_value, round_off, notes, total, paid_amount, voided_at, void_reason,
  converted_from_uuid, photo_path, created_at, updated_at)
VALUES (@b2, UUID(), 'challan', 2, 'UE/DC/26-27/002', 'Krishna Precision', NULL, '2026-08-14', '',
  'none', 0, 0, '', 0, 0, NULL,
  '', NULL, NULL, '2026-08-14 09:25:00', '2026-08-14 09:25:00');
SET @inv := LAST_INSERT_ID();
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'MIG Welding', 2, 2275, 0, '2026-08-14 09:25:00', '2026-08-14 09:25:00');
INSERT INTO invoice_lines (invoice_id, uuid, particulars, quantity, rate, position, created_at, updated_at) VALUES (@inv, UUID(), 'Assembly Charges', 36, 1500, 1, '2026-08-14 09:25:00', '2026-08-14 09:25:00');

