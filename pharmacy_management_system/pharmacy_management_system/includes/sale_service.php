<?php
/**
 * Creates one sale with many medicine lines, safely.
 * $items = [medicine_id => quantity]. Throws RuntimeException with a friendly message on problems.
 * Everything happens in one transaction: either the whole sale is saved, or nothing is.
 */
function create_sale(PDO $pdo, ?int $customerId, int $userId, string $payment, array $items, ?int $prescriptionId = null): int
{
    if (!in_array($payment, ['cash', 'card', 'medical_aid'], true)) {
        $payment = 'cash';
    }
    ksort($items); // same lock order every time prevents deadlocks

    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT * FROM medicines WHERE id = ? AND active = 1 FOR UPDATE');
        $lines = [];
        $totalCents = 0;

        foreach ($items as $medicineId => $qty) {
            $find->execute([$medicineId]);
            $m = $find->fetch();
            if (!$m) throw new RuntimeException('A selected medicine no longer exists.');
            if ($m['expiry_date'] < date('Y-m-d')) throw new RuntimeException($m['name'] . ' is expired and cannot be sold.');
            if ($qty > $m['quantity']) throw new RuntimeException('Not enough stock for ' . $m['name'] . '. Available: ' . $m['quantity']);

            $totalCents += (int)round($m['price'] * 100) * $qty;
            $lines[] = [$medicineId, $qty, $m['price']];
        }

        $pdo->prepare('INSERT INTO sales (customer_id, user_id, total, payment_method) VALUES (?,?,?,?)')
            ->execute([$customerId, $userId, $totalCents / 100, $payment]);
        $saleId = (int)$pdo->lastInsertId();

        $addItem = $pdo->prepare('INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price) VALUES (?,?,?,?)');
        $reduce  = $pdo->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
        foreach ($lines as [$medicineId, $qty, $price]) {
            $addItem->execute([$saleId, $medicineId, $qty, $price]);
            $reduce->execute([$qty, $medicineId]);
        }

        if ($prescriptionId) {
            $mark = $pdo->prepare("UPDATE prescriptions SET status = 'dispensed', sale_id = ? WHERE id = ? AND status = 'pending'");
            $mark->execute([$saleId, $prescriptionId]);
            if ($mark->rowCount() !== 1) throw new RuntimeException('This prescription was already dispensed or cancelled.');
        }

        $pdo->commit();
        return $saleId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
