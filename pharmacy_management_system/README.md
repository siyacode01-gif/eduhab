# PharmaCare - Pharmacy Management System

HTML, CSS, JavaScript, PHP and MySQL. Built for VS Code + XAMPP (Apache + MySQL) + phpMyAdmin.

## Setup
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy this folder to `C:\xampp\htdocs\pharmacy_management_system`.
3. Open http://localhost/phpmyadmin, go to **Import**, choose `database/pharmacy_management.sql`, click **Import**.
   (Importing again wipes all data and starts fresh.)
4. Open http://localhost/pharmacy_management_system/install.php **once** to create the demo staff accounts, then **delete `install.php`**.
5. Open http://localhost/pharmacy_management_system/ and sign in.

## Demo accounts (fictional, for testing only)
| Role | Email | Password |
|---|---|---|
| Admin | admin@pharmacy.local | Admin@123 |
| Manager | manager@pharmacy.local | Manager@123 |
| Pharmacist | pharmacist@pharmacy.local | Pharmacist@123 |
| Assistant | assistant@pharmacy.local | Assistant@123 |
| Cashier | cashier@pharmacy.local | Cashier@123 |
| Stock manager | stock@pharmacy.local | Stock@123 |

## Who can do what (edit `config/auth.php` to change)
| Area | Roles |
|---|---|
| View medicines, alerts, dashboard | everyone |
| Add / edit medicines, add stock | admin, manager, pharmacist, assistant, stock manager |
| Remove medicines | admin, manager, stock manager |
| Customers, sales and receipts | admin, manager, pharmacist, assistant, cashier |
| Capture prescriptions | admin, manager, pharmacist, assistant |
| Dispense prescriptions | admin, pharmacist |
| Suppliers | admin, manager, stock manager |
| Reports | admin, manager |
| Staff accounts | admin |

## Assignment requirements covered
1. Add, update, delete medicines - `staff/medicines.php` (delete hides the medicine so old sales stay correct)
2. Search by name, category or code - medicines page
3. Record and update stock levels - add stock button, automatic deduction on sale
4. Process and record sales, print receipts - `staff/sales.php`, `staff/receipt.php`
5. Log in and manage account - `index.php`, `staff/profile.php`, `staff/users.php`
6. Low-stock alerts - `staff/alerts.php`, dashboard
7. Near-expiry alerts - `staff/alerts.php`, dashboard
8. Success notification - green message after each save or sale
Extra features from the proposal: prescriptions, suppliers, reports (with CSV download).

## Notes
- Expired medicine cannot be sold. Stock can never go below zero.
- Every form has CSRF protection; passwords are stored hashed.
- All data is fictional. Do not enter real patient information.
