-- ---------------------------------------------------------------------------
-- Sync history: handsets, what they moved, and a few changes that lost a
-- last-write-wins comparison so the Conflicts screen has real content.
-- ---------------------------------------------------------------------------

SET @user := (SELECT id FROM users ORDER BY id LIMIT 1);

INSERT INTO devices (user_id, uuid, name, platform, app_version, last_pulled_at, last_pushed_at, last_seen_at, created_at, updated_at)
VALUES
  (@user, UUID(), 'Redmi Note 12',      'android', '1.0.0', '2026-09-14 20:10:00', '2026-09-14 20:11:00', '2026-09-14 20:11:00', '2026-05-02 09:00:00', '2026-09-14 20:11:00'),
  (@user, UUID(), 'Samsung Galaxy M14', 'android', '0.9.4', '2026-08-02 18:40:00', '2026-08-02 18:41:00', '2026-08-02 18:41:00', '2026-04-18 11:30:00', '2026-08-02 18:41:00'),
  (@user, UUID(), 'Office Tablet',      'android', '1.0.0', NULL,                  NULL,                  '2026-09-01 10:05:00', '2026-09-01 10:05:00', '2026-09-01 10:05:00');

SET @dev_redmi   := (SELECT id FROM devices WHERE name = 'Redmi Note 12' LIMIT 1);
SET @dev_samsung := (SELECT id FROM devices WHERE name = 'Samsung Galaxy M14' LIMIT 1);
SET @dev_nord    := (SELECT id FROM devices WHERE name = 'OnePlus Nord' LIMIT 1);

INSERT INTO sync_logs (device_id, user_id, direction, counts, conflicts, notes, created_at, updated_at)
VALUES
  (@dev_nord,    @user, 'push', JSON_OBJECT('invoices', 4, 'payments', 2, 'customers', 1), 0, NULL, '2026-09-14 19:02:00', '2026-09-14 19:02:00'),
  (@dev_nord,    @user, 'pull', JSON_OBJECT('invoices', 9, 'payments', 5),                 0, NULL, '2026-09-14 19:02:20', '2026-09-14 19:02:20'),
  (@dev_redmi,   @user, 'push', JSON_OBJECT('invoices', 2, 'payments', 3),                 1, NULL, '2026-09-14 20:11:00', '2026-09-14 20:11:00'),
  (@dev_redmi,   @user, 'pull', JSON_OBJECT('invoices', 12, 'customers', 4),               0, NULL, '2026-09-14 20:11:30', '2026-09-14 20:11:30'),
  (@dev_samsung, @user, 'push', JSON_OBJECT('invoices', 1),                                2, 'App version 0.9.4 — behind the others.', '2026-08-02 18:41:00', '2026-08-02 18:41:00'),
  (@dev_samsung, @user, 'pull', JSON_OBJECT('invoices', 31, 'payments', 18, 'items', 6),   0, NULL, '2026-08-02 18:41:40', '2026-08-02 18:41:40');

SET @log_redmi   := (SELECT id FROM sync_logs WHERE device_id = @dev_redmi   AND direction = 'push' ORDER BY id DESC LIMIT 1);
SET @log_samsung := (SELECT id FROM sync_logs WHERE device_id = @dev_samsung AND direction = 'push' ORDER BY id DESC LIMIT 1);

-- A customer renamed on an old handset, after the name had already been
-- corrected here. The server's copy stood; the phone's is kept for the record.
INSERT INTO sync_conflicts (sync_log_id, user_id, model_type, uuid, incoming, existing, resolution, reviewed, created_at, updated_at)
SELECT @log_samsung, @user, 'Customer', c.uuid,
       JSON_OBJECT('uuid', c.uuid, 'name', 'Mahesh Trading Co', 'phone', '9876500001',
                   'updated_at', '2026-08-02T18:30:00+05:30'),
       JSON_OBJECT('uuid', c.uuid, 'name', c.name, 'phone', c.phone,
                   'updated_at', '2026-08-10T11:05:00+05:30'),
       'server_kept', 0, '2026-08-02 18:41:00', '2026-08-02 18:41:00'
FROM customers c WHERE c.name = 'Mahesh Traders' LIMIT 1;

-- A rate edited on two phones in the same afternoon.
INSERT INTO sync_conflicts (sync_log_id, user_id, model_type, uuid, incoming, existing, resolution, reviewed, created_at, updated_at)
SELECT @log_samsung, @user, 'Item', it.uuid,
       JSON_OBJECT('uuid', it.uuid, 'name', it.name, 'default_rate', 500,
                   'updated_at', '2026-08-02T16:20:00+05:30'),
       JSON_OBJECT('uuid', it.uuid, 'name', it.name, 'default_rate', it.default_rate,
                   'updated_at', '2026-08-02T17:45:00+05:30'),
       'server_kept', 0, '2026-08-02 18:41:00', '2026-08-02 18:41:00'
FROM items it WHERE it.name = 'Lathe Job Work' LIMIT 1;

-- A bill edited offline while the office had already added a line to it.
INSERT INTO sync_conflicts (sync_log_id, user_id, model_type, uuid, incoming, existing, resolution, reviewed, created_at, updated_at)
SELECT @log_redmi, @user, 'Invoice', i.uuid,
       JSON_OBJECT('uuid', i.uuid, 'customer_name', i.customer_name, 'bill_ref', i.bill_ref,
                   'notes', 'Qty corrected to 38 on site', 'updated_at', '2026-09-14T15:10:00+05:30'),
       JSON_OBJECT('uuid', i.uuid, 'customer_name', i.customer_name, 'bill_ref', i.bill_ref,
                   'notes', i.notes, 'updated_at', '2026-09-14T18:55:00+05:30'),
       'server_kept', 0, '2026-09-14 20:11:00', '2026-09-14 20:11:00'
FROM invoices i WHERE i.doc_type = 'bill' AND i.voided_at IS NULL ORDER BY i.date DESC LIMIT 1;

-- One already dealt with, so the screen isn't only a backlog.
INSERT INTO sync_conflicts (sync_log_id, user_id, model_type, uuid, incoming, existing, resolution, reviewed, created_at, updated_at)
SELECT @log_samsung, @user, 'Customer', c.uuid,
       JSON_OBJECT('uuid', c.uuid, 'name', c.name, 'phone', '9999999999',
                   'updated_at', '2026-07-30T09:00:00+05:30'),
       JSON_OBJECT('uuid', c.uuid, 'name', c.name, 'phone', c.phone,
                   'updated_at', '2026-08-01T12:00:00+05:30'),
       'server_kept', 1, '2026-08-02 18:41:00', '2026-08-03 09:15:00'
FROM customers c WHERE c.name = 'Kiran Engineering' LIMIT 1;
