<?php

namespace Tests\Unit\Property\BankCollections;

use App\Services\Property\BankCollections\CoopIpnNotification;
use PHPUnit\Framework\TestCase;

class CoopIpnNotificationTest extends TestCase
{
    public function test_b2b_ipn_sample_reads_account_phone_and_name_from_narration(): void
    {
        $notice = CoopIpnNotification::normalize([
            'AcctNo' => 'ACCOUNT_NUMBER',
            'Amount' => '900.0',
            'BookedBalance' => '5910822.62',
            'ClearedBalance' => '5910822.62',
            'Currency' => 'KES',
            'CustMemoLine1' => 'UGDFHAVTTZ~7719#Isaac~254',
            'CustMemoLine2' => '722417771~MPESAC2B_400222',
            'CustMemoLine3' => '~IBRAHIM NDWIGA',
            'EventType' => 'CREDIT',
            'ExchangeRate' => '',
            'Narration' => 'UGDFHAVTTZ~7719#Isaac~254722417771~MPESAC2B_400222~IBRAHIM NDWIGA',
            'PaymentRef' => '13072026_158467093',
            'PostingDate' => '2026-07-13',
            'ValueDate' => '2026-07-13',
            'TransactionDate' => '2026-07-13T14:22:04',
            'TransactionId' => 'CB0545802_13072026_2',
        ]);

        $this->assertTrue($notice['is_credit']);
        $this->assertSame('CB0545802_13072026_2', $notice['transaction_id']);
        $this->assertSame(900.0, $notice['amount']);
        $this->assertSame('ACCOUNT_NUMBER', $notice['bank_account_number']);
        $this->assertSame('7719', $notice['payer_reference']);
        $this->assertSame('254722417771', $notice['payer_phone']);
        $this->assertSame('IBRAHIM NDWIGA', $notice['payer_name']);
        $this->assertSame('13072026_158467093', $notice['ingest']['provider_reference']);
        $this->assertSame('7719', $notice['ingest']['tenant_account_number']);
        $this->assertStringStartsWith('2026-07-13', (string) $notice['transaction_date']);
    }

    public function test_tenant_ac_no_in_narration_wins_over_the_short_code(): void
    {
        $notice = CoopIpnNotification::normalize([
            'AcctNo' => '01123456789000',
            'Amount' => '12000.00',
            'EventType' => 'CREDIT',
            'Narration' => 'UGDFHAVTTZ~TNT001324#Isaac~254712000111~MPESAC2B_400222~PHANICE OSIEKO',
            'PaymentRef' => '13072026_158467093',
            'TransactionId' => '8963478382745',
            'TransactionDate' => '20190301165420',
        ]);

        $this->assertSame('TNT001324', $notice['payer_reference']);
        $this->assertSame('254712000111', $notice['payer_phone']);
        $this->assertSame('8963478382745', $notice['ingest']['external_transaction_reference']);
    }

    public function test_debit_is_not_treated_as_a_collection(): void
    {
        $notice = CoopIpnNotification::normalize([
            'TransactionId' => 'DEB-1',
            'Amount' => '500.00',
            'EventType' => 'DEBIT',
            'AcctNo' => '01123456789000',
            'Narration' => 'TNT001324',
        ]);

        $this->assertFalse($notice['is_credit']);
    }

    public function test_acknowledgement_matches_the_b2b_ipn_success_body(): void
    {
        $ack = CoopIpnNotification::acknowledgement('200', 'Successfully received data');

        $this->assertSame([
            'MessageCode' => '200',
            'Message' => 'Successfully received data',
        ], $ack);
    }
}
