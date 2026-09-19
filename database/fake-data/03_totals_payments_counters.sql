-- ---------------------------------------------------------------------------
-- Compute the cached money columns in SQL, mirroring the Invoice model:
--   subtotal -> discount (clamped) -> taxable -> tax -> round-off -> total
-- Each tax row is rounded on its own before summing, exactly as taxAmountFor()
-- does, so the server and the handset cannot disagree by a paisa.
-- Timestamps are deliberately untouched: these columns are derived, not edited.
-- ---------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_calc;
CREATE TEMPORARY TABLE tmp_calc (
  id BIGINT UNSIGNED PRIMARY KEY,
  subtotal DECIMAL(14,2) DEFAULT 0,
  discount DECIMAL(14,2) DEFAULT 0,
  taxable  DECIMAL(14,2) DEFAULT 0,
  tax_total DECIMAL(14,2) DEFAULT 0
);

INSERT INTO tmp_calc (id, subtotal)
SELECT i.id,
       ROUND(COALESCE((SELECT SUM(l.quantity * l.rate)
                       FROM invoice_lines l WHERE l.invoice_id = i.id), 0), 2)
FROM invoices i;

UPDATE tmp_calc c JOIN invoices i ON i.id = c.id
SET c.discount = ROUND(GREATEST(0, LEAST(c.subtotal,
      CASE i.discount_type
        WHEN 'percent' THEN c.subtotal * i.discount_value / 100
        WHEN 'amount'  THEN i.discount_value
        ELSE 0
      END)), 2);

UPDATE tmp_calc SET taxable = ROUND(subtotal - discount, 2);

UPDATE tmp_calc c
SET c.tax_total = ROUND(COALESCE((SELECT SUM(ROUND(c.taxable * t.percent / 100, 2))
                                  FROM invoice_taxes t WHERE t.invoice_id = c.id), 0), 2);

UPDATE invoices i JOIN tmp_calc c ON c.id = i.id
SET i.total = ROUND(c.taxable + c.tax_total + i.round_off, 2);

-- ---------------------------------------------------------------------------
-- Payments, as a fraction of the total just computed. Deterministic by id so
-- the spread of settled / part-settled / untouched bills is reproducible.
-- Quotations, challans and cancelled bills never take a payment.
-- ---------------------------------------------------------------------------

-- Settled in full.
INSERT INTO payments (invoice_id, uuid, date, amount, mode, note, created_at, updated_at)
SELECT i.id, UUID(),
       LEAST(DATE_ADD(i.date, INTERVAL (6 + (i.id % 25)) DAY), '2026-09-15'),
       i.total,
       ELT(1 + (i.id % 4), 'cash', 'upi', 'cheque', 'bank'),
       CASE (i.id % 4)
         WHEN 0 THEN ''
         WHEN 1 THEN CONCAT('GPay ref ', 4000 + i.id)
         WHEN 2 THEN CONCAT('Cheque ', 400000 + i.id * 7)
         ELSE CONCAT('NEFT ', 50000 + i.id * 3)
       END,
       LEAST(DATE_ADD(i.date, INTERVAL (6 + (i.id % 25)) DAY), '2026-09-15'),
       LEAST(DATE_ADD(i.date, INTERVAL (6 + (i.id % 25)) DAY), '2026-09-15')
FROM invoices i
WHERE i.doc_type = 'bill' AND i.voided_at IS NULL AND i.total > 0 AND i.id % 3 = 0;

-- Part-settled: something came in, the rest is still owed.
INSERT INTO payments (invoice_id, uuid, date, amount, mode, note, created_at, updated_at)
SELECT i.id, UUID(),
       LEAST(DATE_ADD(i.date, INTERVAL (9 + (i.id % 18)) DAY), '2026-09-15'),
       ROUND(i.total * ELT(1 + (i.id % 4), 0.30, 0.40, 0.50, 0.60), 2),
       ELT(1 + (i.id % 3), 'cash', 'upi', 'cheque'),
       CASE (i.id % 3)
         WHEN 0 THEN 'Part payment'
         WHEN 1 THEN CONCAT('GPay ref ', 7000 + i.id)
         ELSE CONCAT('Cheque ', 700000 + i.id * 3)
       END,
       LEAST(DATE_ADD(i.date, INTERVAL (9 + (i.id % 18)) DAY), '2026-09-15'),
       LEAST(DATE_ADD(i.date, INTERVAL (9 + (i.id % 18)) DAY), '2026-09-15')
FROM invoices i
WHERE i.doc_type = 'bill' AND i.voided_at IS NULL AND i.total > 0 AND i.id % 3 = 1;

-- A second instalment on a few of those, so some bills show a real history.
INSERT INTO payments (invoice_id, uuid, date, amount, mode, note, created_at, updated_at)
SELECT i.id, UUID(),
       LEAST(DATE_ADD(i.date, INTERVAL (26 + (i.id % 14)) DAY), '2026-09-15'),
       ROUND(i.total * 0.20, 2),
       'bank', CONCAT('NEFT ', 90000 + i.id),
       LEAST(DATE_ADD(i.date, INTERVAL (26 + (i.id % 14)) DAY), '2026-09-15'),
       LEAST(DATE_ADD(i.date, INTERVAL (26 + (i.id % 14)) DAY), '2026-09-15')
FROM invoices i
WHERE i.doc_type = 'bill' AND i.voided_at IS NULL AND i.total > 0
  AND i.id % 3 = 1 AND i.id % 7 = 0;

-- Cache the paid total from the rows that determine it.
UPDATE invoices i
SET i.paid_amount = ROUND(COALESCE((SELECT SUM(p.amount) FROM payments p
                                    WHERE p.invoice_id = i.id AND p.deleted_at IS NULL), 0), 2);

-- ---------------------------------------------------------------------------
-- Drag every counter past the highest number actually issued, so a document
-- raised through the API can never collide with one of these.
-- ---------------------------------------------------------------------------

UPDATE businesses b SET
  b.next_bill_no = GREATEST(b.next_bill_no,
    COALESCE((SELECT MAX(i.bill_no) FROM invoices i
              WHERE i.business_id = b.id AND i.doc_type = 'bill'), 0) + 1),
  b.next_quote_no = GREATEST(b.next_quote_no,
    COALESCE((SELECT MAX(i.bill_no) FROM invoices i
              WHERE i.business_id = b.id AND i.doc_type = 'quotation'), 0) + 1),
  b.next_challan_no = GREATEST(b.next_challan_no,
    COALESCE((SELECT MAX(i.bill_no) FROM invoices i
              WHERE i.business_id = b.id AND i.doc_type = 'challan'), 0) + 1);

DROP TEMPORARY TABLE IF EXISTS tmp_calc;
