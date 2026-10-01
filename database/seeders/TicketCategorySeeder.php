<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    /**
     * @var array<string, string> name => description
     */
    public const CATEGORIES = [
        'Hardware' => 'Laptops, desktops, monitors, peripherals and other physical equipment.',
        'Software' => 'Application installation, licensing, errors and updates.',
        'Network' => 'Wi-Fi, LAN, VPN and internet connectivity problems.',
        'Email' => 'Mailbox, calendar, distribution list and email client issues.',
        'Account & Access' => 'Password resets, new accounts, permissions and access requests.',
        'Security' => 'Phishing, malware, suspicious activity and security incidents.',
        'Printer' => 'Printing, scanning and copier problems.',
        'System Issue' => 'Server, internal system or service outages and malfunctions.',
        'Other' => 'Anything that does not fit another category.',
    ];

    /**
     * Idempotent: safe to run repeatedly; rows are matched on the unique name.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            TicketCategory::withTrashed()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true],
            );
        }
    }
}
