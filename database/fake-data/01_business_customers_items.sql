-- Fake data, inserted directly. No seeder involved.
SET NAMES utf8mb4;
SET @user := (SELECT id FROM users ORDER BY id LIMIT 1);
SET @b1 := (SELECT id FROM businesses WHERE name = 'Rajesh Steel Works' LIMIT 1);

-- A second company, so the admin's business filter and the cross-company
-- totals on the dashboard have something real to do.
INSERT INTO businesses
 (user_id, uuid, name, tagline, address, mobile, jurisdiction_text, gst_number, email,
  bank_details, logo_path, signature_path, upi_id, next_bill_no, next_quote_no, next_challan_no,
  bill_prefix, fy_reset, bill_fy, terms_text, created_at, updated_at)
VALUES
 (@user, UUID(), 'Umiya Engineering Works', 'Fabrication | CNC | Sheet Metal',
  'Shed 7, Odhav Industrial Estate, Ahmedabad 382415', '9825011223',
  'Subject to Ahmedabad Jurisdiction', '24AACFU9911K1Z8', 'accounts@umiyaeng.in',
  'Bank of Baroda, Odhav Branch\nA/c 3901020000455\nIFSC BARB0ODHAVX', NULL, NULL,
  'umiyaeng@okaxis', 1, 1, 1, 'UE', 1, '26-27',
  'Payment within 15 days. Interest @18% p.a. on delayed payments.', NOW(), NOW());
SET @b2 := LAST_INSERT_ID();

-- Customers
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Gayatri Engineering', '9825044551', 'Plot 22, Narol, Ahmedabad', '24AAGCG2211M1Z4', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Sardar Iron Works', '9898123344', 'Naroda Road, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Bhavani Auto Parts', '9723388110', 'Vatva Phase 3, Ahmedabad', '24AABCB7654P1ZS', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Jay Ambe Fabricators', '9427551190', 'Aslali, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Navkar Industries', '9909887766', 'Changodar, Ahmedabad', '24AAJCN3344Q1ZK', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Rameshbhai Patel', '9376112244', 'Bapunagar, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Shakti Metal Corporation', '9662200553', 'Rakhial, Ahmedabad', '24AAKCS8877R1ZD', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b1, UUID(), 'Dhanlaxmi Traders', '9558877221', 'Amraiwadi, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Arihant Steel Centre', '9824477110', 'Odhav Ring Road, Ahmedabad', '24AAFCA5566T1ZP', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Krishna Precision', '9913366442', 'Kathwada GIDC, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Om Sai Enterprise', '9737711098', 'Nikol, Ahmedabad', '24AAGCO1122V1ZM', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Parshwanath Engineering', '9879922113', 'Vastral, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Suryadeep Industries', '9426633880', 'Kubernagar, Ahmedabad', '24AALCS4455W1ZB', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Mehul Fabrication', '9099445577', 'Ramol, Ahmedabad', '', NOW(), NOW());
INSERT INTO customers (business_id, uuid, name, phone, address, gst_number, created_at, updated_at) VALUES (@b2, UUID(), 'Tirupati Engineers', '9714488220', 'Singarva, Ahmedabad', '', NOW(), NOW());

-- Rate catalogue
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b1, UUID(), 'Shaping Work', 520, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b1, UUID(), 'Drilling Work', 290, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b1, UUID(), 'Heat Treatment', 1150, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b1, UUID(), 'Key Way Cutting', 640, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b1, UUID(), 'Hydraulic Press Work', 1850, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'CNC Turning', 980, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'Laser Cutting', 1320, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'Sheet Bending', 410, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'MIG Welding', 2250, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'Powder Coating', 760, '998873', NOW(), NOW());
INSERT INTO items (business_id, uuid, name, default_rate, hsn_code, created_at, updated_at) VALUES (@b2, UUID(), 'Assembly Charges', 1500, '998873', NOW(), NOW());

