<?php

namespace App\Services;

use App\Repositories\BillingRepository;
use App\Repositories\CustomerRepository;
use App\Core\Database;
use PDO;

/**
 * Billing Business Logic & Payment Processing Service
 */
class BillingService
{
    private BillingRepository $billingRepo;
    private CustomerRepository $customerRepo;
    private PDO $pdo;

    public function __construct(
        ?BillingRepository $billingRepo = null,
        ?CustomerRepository $customerRepo = null,
        ?PDO $pdo = null
    ) {
        $this->billingRepo  = $billingRepo ?: new BillingRepository();
        $this->customerRepo = $customerRepo ?: new CustomerRepository();
        $this->pdo          = $pdo ?: Database::getConnection();
    }

    public function getBillings(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return $this->billingRepo->all($filters, $limit, $offset);
    }

    public function countBillings(array $filters = []): int
    {
        return $this->billingRepo->count($filters);
    }

    public function getBilling(int $id): ?array
    {
        return $this->billingRepo->find($id);
    }

    /**
     * Generate monthly invoices for all active customers
     */
    public function generateMonthlyBills(string $period): int
    {
        $active = $this->pdo->query("
            SELECT c.id, c.billing_due_date, c.discount, p.price,
                   CONCAT('{$period}-', LPAD(c.billing_due_date, 2, '0')) AS due
            FROM customers c 
            JOIN packages p ON p.id = c.package_id
            WHERE c.status = 'active'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $createdCount = 0;
        foreach ($active as $cust) {
            if (!$this->billingRepo->exists((int)$cust['id'], $period)) {
                $discountAmt = min((int)$cust['discount'], (int)$cust['price']);
                $finalAmount = (int)$cust['price'] - $discountAmt;

                $this->billingRepo->create([
                    'customer_id'     => (int)$cust['id'],
                    'period'          => $period,
                    'amount'          => $finalAmount,
                    'discount_amount' => $discountAmt,
                    'status'          => 'unpaid',
                    'due_date'        => $cust['due'],
                ]);
                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Record payment for an invoice
     */
    public function recordPayment(int $billingId, float $amount, string $method, ?string $notes = null, ?int $userId = null): bool
    {
        $this->billingRepo->updateStatus($billingId, 'paid');
        $this->billingRepo->recordPayment([
            'billing_id'           => $billingId,
            'processed_by_user_id' => $userId,
            'amount_paid'          => $amount,
            'method'               => $method,
            'notes'                => $notes ?? '',
        ]);
        return true;
    }

    /**
     * Cancel an invoice
     */
    public function cancelBill(int $billingId): bool
    {
        return $this->billingRepo->updateStatus($billingId, 'cancelled');
    }

    public function getSummary(string $period): array
    {
        return $this->billingRepo->getSummary($period);
    }

    public function getPayments(int $billingId): array
    {
        return $this->billingRepo->getPayments($billingId);
    }
}

