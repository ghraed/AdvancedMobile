<?php

namespace Tests\Feature;

use App\Models\InstallmentAccount;
use App\Models\InstallmentApplication;
use App\Models\InstallmentPayment;
use App\Models\User;
use App\Services\InstallmentAccountService;
use App\Services\InstallmentPaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstallmentManagementPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_approved_customer_application_can_create_one_account(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = InstallmentApplication::factory()->create(['status' => 'submitted']);
        $service = app(InstallmentAccountService::class);

        $this->expectException(DomainException::class);
        $service->activate($submitted, '2026-01-31', $admin);
    }

    public function test_activation_uses_snapshot_cents_and_month_end_schedule(): void
    {
        $account = $this->account(['product_price' => '100.00', 'total_payable' => '100.01', 'installment_months' => 3], '2026-01-31');

        $this->assertSame([3333, 3333, 3335], $account->scheduleItems->pluck('amount_due_cents')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $account->scheduleItems->pluck('due_date')->map->toDateString()->all());
        $this->assertSame(10001, $account->scheduleItems->sum('amount_due_cents'));

        $this->expectException(DomainException::class);
        app(InstallmentAccountService::class)->activate($account->application, '2026-01-31', $account->creator);
    }

    public function test_three_six_and_nine_payment_schedules_preserve_every_cent(): void
    {
        foreach ([3, 6, 9] as $count) {
            $account = $this->account(['total_payable' => '999.99', 'installment_months' => $count]);
            $this->assertCount($count, $account->scheduleItems);
            $this->assertSame(99999, $account->scheduleItems->sum('amount_due_cents'));
        }
    }

    public function test_partial_and_spanning_payment_allocates_oldest_first(): void
    {
        $account = $this->account(['product_price' => '300.00', 'total_payable' => '300.00']);
        $payment = $this->pay($account, 15000);
        $items = $account->scheduleItems()->get();

        $this->assertSame([10000, 5000, 0], $items->pluck('amount_paid_cents')->all());
        $this->assertSame(['paid', 'partial', 'upcoming'], $items->pluck('status')->all());
        $this->assertSame(2, $payment->allocations()->count());
        $this->assertSame(15000, $account->fresh()->amount_paid_cents);
    }

    public function test_invalid_or_excess_payment_leaves_account_and_schedule_unchanged(): void
    {
        $account = $this->account();
        foreach ([0, -1, $account->total_payable_cents + 1] as $amount) {
            try {
                $this->pay($account, $amount);
                $this->fail('Expected invalid payment to fail.');
            } catch (DomainException) {
                $this->assertSame(0, $account->fresh()->amount_paid_cents);
                $this->assertSame(0, $account->scheduleItems()->sum('amount_paid_cents'));
                $this->assertDatabaseCount('installment_payments', 0);
            }
        }
    }

    public function test_allocation_failure_rolls_back_payment_and_partial_schedule_changes(): void
    {
        $account = $this->account(['product_price' => '300.00', 'total_payable' => '300.00']);
        $account->scheduleItems()->where('installment_number', 3)->delete();

        try {
            $this->pay($account, 30000);
            $this->fail('Expected inconsistent schedule allocation to fail.');
        } catch (DomainException) {
            $this->assertDatabaseCount('installment_payments', 0);
            $this->assertSame(0, $account->scheduleItems()->sum('amount_paid_cents'));
            $this->assertSame(['upcoming', 'upcoming'], $account->scheduleItems()->pluck('status')->all());
            $this->assertSame(0, $account->fresh()->amount_paid_cents);
        }
    }

    public function test_duplicate_idempotency_key_creates_one_payment(): void
    {
        $account = $this->account();
        $admin = User::factory()->admin()->create();
        $service = app(InstallmentPaymentService::class);
        $data = ['amount_cents' => 1000, 'payment_method' => 'cash', 'idempotency_key' => 'same-request'];

        $first = $service->recordPayment($account, $data, $admin);
        $second = $service->recordPayment($account, $data, $admin);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('installment_payments', 1);
        $this->assertSame(1000, $account->fresh()->amount_paid_cents);
    }

    public function test_final_payment_completes_and_reversal_reopens_account(): void
    {
        $account = $this->account(['product_price' => '90.00', 'total_payable' => '90.00']);
        $payment = $this->pay($account, 9000);
        $account->refresh();
        $this->assertSame('completed', $account->account_status);
        $this->assertNotNull($account->completed_at);

        $admin = User::factory()->admin()->create();
        app(InstallmentPaymentService::class)->reversePayment($payment, $admin, 'Wrong tender amount');
        $account->refresh();
        $this->assertSame('active', $account->account_status);
        $this->assertNull($account->completed_at);
        $this->assertSame(9000, $account->remaining_balance_cents);
        $this->assertNotNull($payment->fresh()->reversed_at);

        $this->expectException(DomainException::class);
        app(InstallmentPaymentService::class)->reversePayment($payment, $admin, 'Again');
    }

    public function test_completed_and_cancelled_accounts_reject_new_payments_and_keep_history(): void
    {
        $account = $this->account(['product_price' => '90.00', 'total_payable' => '90.00']);
        $this->pay($account, 1000);
        app(InstallmentAccountService::class)->cancel($account, $account->creator, 'Customer request');
        $this->assertDatabaseCount('installment_payments', 1);
        $this->assertDatabaseHas('installment_account_events', ['event_type' => 'account_cancelled']);

        $this->expectException(DomainException::class);
        $this->pay($account, 1000);
    }

    public function test_effective_overdue_state_is_dynamic_for_unpaid_partial_and_paid_items(): void
    {
        Carbon::setTestNow('2026-04-01 12:00:00');
        $account = $this->account(['product_price' => '300.00', 'total_payable' => '300.00'], '2026-01-01');
        $this->assertSame('overdue', $account->scheduleItems[0]->effective_status);
        $this->pay($account, 5000);
        $this->assertSame('overdue', $account->scheduleItems()->first()->effective_status);
        $this->pay($account->fresh(), 5000);
        $this->assertSame('paid', $account->scheduleItems()->first()->effective_status);
    }

    public function test_customer_account_and_receipt_authorization_blocks_idor(): void
    {
        $ownerAccount = $this->account();
        $otherAccount = $this->account();
        $payment = $this->pay($ownerAccount, 1000);

        $this->actingAs($ownerAccount->user)->get(route('account.installments.show', $ownerAccount))->assertOk();
        $this->actingAs($otherAccount->user)->get(route('account.installments.show', $ownerAccount))->assertForbidden();
        $this->actingAs($ownerAccount->user)->get(route('account.installments.payments.receipt', [$ownerAccount, $payment]))->assertOk()->assertSee($payment->receipt_number);
        $this->actingAs($otherAccount->user)->get(route('account.installments.payments.receipt', [$ownerAccount, $payment]))->assertForbidden();
    }

    public function test_admin_routes_deny_customer_and_allow_admin_with_filters(): void
    {
        Carbon::setTestNow('2026-04-01');
        $account = $this->account(['product_name_snapshot' => 'Filter Phone'], '2026-01-01');
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($customer)->get(route('admin.installments.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.installments.index', ['status' => 'active', 'overdue' => 1, 'customer' => $account->user->email, 'product' => 'Filter']))->assertOk()->assertSee($account->account_number);
        $this->actingAs($admin)->get(route('admin.installments.show', $account))->assertOk()->assertSee('Record payment');
    }

    private function account(array $attributes = [], string $firstDue = '2026-01-15'): InstallmentAccount
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $application = InstallmentApplication::factory()->for($customer)->create(array_merge([
            'status' => 'approved',
            'product_price' => '300.00',
            'total_payable' => '300.00',
            'monthly_payment' => '100.00',
            'installment_months' => 3,
            'approved_at' => now(),
        ], $attributes));

        return app(InstallmentAccountService::class)->activate($application, $firstDue, $admin);
    }

    private function pay(InstallmentAccount $account, int $amountCents): InstallmentPayment
    {
        return app(InstallmentPaymentService::class)->recordPayment($account, [
            'amount_cents' => $amountCents,
            'payment_method' => 'cash',
            'idempotency_key' => fake()->uuid(),
        ], User::factory()->admin()->create());
    }
}
