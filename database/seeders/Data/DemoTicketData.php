<?php

namespace Database\Seeders\Data;

use App\Enums\TicketPriorityLevel as P;
use App\Enums\TicketStatusSlug as S;

/**
 * DEVELOPMENT-ONLY demo dataset (fictional people, example.com addresses).
 *
 * Kept as plain data so the seeders stay small and the content is easy to review.
 * All timestamps are FIXED (UTC) so seeding is deterministic and re-runnable.
 *
 * Each ticket:
 *   n          sequence number -> ticket_number INC-2026-0000NN
 *   by         requester e-mail (department is copied from the requester)
 *   category / priority / status
 *   created    ticket creation time
 *   resolved / closed   lifecycle timestamps (only for Resolved / Closed)
 *   assignments  chronological history; each new entry CLOSES the previous one
 *                (the last entry stays open = current assignee)
 *   comments     chronological conversation; 'internal' => IT-only note
 */
final class DemoTicketData
{
    private const ADMIN = 'admin@example.com';

    private const SUP1 = 'support1@example.com';

    private const SUP2 = 'support2@example.com';

    private const SUP3 = 'support3@example.com';

    /**
     * @return list<array<string, mixed>>
     */
    public static function tickets(): array
    {
        return [
            // ───────────────────────────── CLOSED ─────────────────────────────
            [
                'n' => 1, 'by' => 'employee1@example.com', 'category' => 'Account & Access', 'priority' => P::Medium, 'status' => S::Closed,
                'title' => 'Cannot access shared finance folder',
                'description' => "Since this morning I get 'Access denied' when opening \\\\fs01\\Finance\\Reports. I need the month-end reports folder to finish the close.",
                'created' => '2026-08-11 09:10', 'resolved' => '2026-08-11 10:25', 'closed' => '2026-08-12 10:30',
                'resolution' => 'User was removed from the FIN-Shared-RW group during the quarterly access review. Access restored after manager approval.',
                'assignments' => [[self::SUP2, self::ADMIN, '2026-08-11 09:40', 'Access issue — assigning to Emily.']],
                'comments' => [
                    ['employee1@example.com', '2026-08-11 09:12', 'I started experiencing this issue this morning. It worked fine yesterday.'],
                    [self::SUP2, '2026-08-11 09:55', 'Thanks Priya, I am checking your group membership now.'],
                    [self::SUP2, '2026-08-11 10:05', 'User was removed from FIN-Shared-RW in the quarterly access review. Re-adding after confirming with the Finance manager.', true],
                    [self::SUP2, '2026-08-11 10:20', 'I have restored your access. Please sign out and back in, then try the folder again.'],
                    ['employee1@example.com', '2026-08-11 10:45', 'Working now, thank you!'],
                ],
            ],
            [
                'n' => 2, 'by' => 'employee2@example.com', 'category' => 'Printer', 'priority' => P::Low, 'status' => S::Closed,
                'title' => 'Printer not responding on the Finance floor',
                'description' => 'The shared printer FIN-PRN-02 shows as offline and none of our jobs are printing. Several of us are affected.',
                'created' => '2026-08-14 14:05', 'resolved' => '2026-08-14 16:00', 'closed' => '2026-08-16 09:00',
                'resolution' => 'Print spooler was stuck. Cleared the queue and restarted the print server service; printer back online.',
                'assignments' => [[self::SUP3, self::ADMIN, '2026-08-14 14:30', null]],
                'comments' => [
                    [self::SUP3, '2026-08-14 14:40', 'Hi Daniel, I can see the printer is offline from the print server. Looking into it.'],
                    [self::SUP3, '2026-08-14 15:50', 'Print spooler was jammed with a corrupted job. Cleared and restarted. Please try printing again.'],
                    ['employee2@example.com', '2026-08-14 16:10', 'Printing again, thanks for the quick fix.'],
                ],
            ],
            [
                'n' => 3, 'by' => 'employee3@example.com', 'category' => 'Account & Access', 'priority' => P::Medium, 'status' => S::Closed,
                'title' => 'New employee account access request',
                'description' => 'A new HR coordinator starts on Monday 24 August. She needs a network account, e-mail, and access to the HRIS and the HR shared drive.',
                'created' => '2026-08-18 08:30', 'resolved' => '2026-08-19 15:00', 'closed' => '2026-08-21 09:00',
                'resolution' => 'Account, mailbox, HRIS role and HR share permissions created per the approved onboarding checklist.',
                'assignments' => [[self::SUP1, self::ADMIN, '2026-08-18 08:45', 'Onboarding request.']],
                'comments' => [
                    [self::SUP1, '2026-08-18 09:15', 'Thanks Amira. Could you confirm the new starter’s manager and the exact HRIS role she needs?'],
                    ['employee3@example.com', '2026-08-18 09:40', 'Manager is me. She needs the standard HR Coordinator role in the HRIS.'],
                    [self::SUP1, '2026-08-19 14:50', 'All accounts are created. Temporary credentials will be handed over in person on her first day.'],
                ],
            ],
            [
                'n' => 4, 'by' => 'employee4@example.com', 'category' => 'Network', 'priority' => P::High, 'status' => S::Closed,
                'title' => 'Unable to connect to company VPN',
                'description' => 'I am working from home and the VPN client fails with "Authentication failed" every time. I have not changed my password. I have a customer demo this afternoon.',
                'created' => '2026-08-20 07:50', 'resolved' => '2026-08-20 16:30', 'closed' => '2026-08-23 09:00',
                'resolution' => 'The user VPN certificate had expired. Issued a new certificate and re-provisioned the VPN profile.',
                'assignments' => [
                    [self::SUP1, self::ADMIN, '2026-08-20 08:10', 'Remote access issue.'],
                    [self::SUP2, self::SUP1, '2026-08-20 13:00', 'Handing over at end of shift — network follow-up needed.'],
                ],
                'comments' => [
                    ['employee4@example.com', '2026-08-20 07:55', 'I have restarted the laptop but the issue remains.'],
                    [self::SUP1, '2026-08-20 08:20', 'Please try reconnecting to the VPN and let me know whether the connection succeeds.'],
                    ['employee4@example.com', '2026-08-20 08:35', 'Same error again.'],
                    [self::SUP1, '2026-08-20 09:05', 'VPN gateway logs show repeated authentication failures for this account — suspect an expired client certificate.', true],
                    [self::SUP2, '2026-08-20 13:15', 'Hi Kevin, I am taking over. Your VPN certificate expired; I am issuing a new one now.'],
                    [self::SUP2, '2026-08-20 16:20', 'New profile pushed to your laptop. Please reconnect.'],
                    ['employee4@example.com', '2026-08-20 16:28', 'Connected! Thank you.'],
                ],
            ],
            [
                'n' => 5, 'by' => 'employee5@example.com', 'category' => 'Hardware', 'priority' => P::Medium, 'status' => S::Closed,
                'title' => 'Laptop running extremely slowly',
                'description' => 'My laptop takes more than ten minutes to start and every application freezes. It has been getting worse over the last two weeks.',
                'created' => '2026-08-25 10:00', 'resolved' => '2026-08-27 11:30', 'closed' => '2026-08-30 09:00',
                'resolution' => 'Disk was 98% full and the SSD showed reallocated sectors. Data migrated to a replacement SSD and Windows reinstalled from the corporate image.',
                'assignments' => [[self::SUP3, self::SUP3, '2026-08-25 10:20', 'Self-assigned.']],
                'comments' => [
                    [self::SUP3, '2026-08-25 10:30', 'Hi Nadia, please bring the laptop to the IT desk when you can so I can run diagnostics.'],
                    ['employee5@example.com', '2026-08-25 11:15', 'I will drop it off after lunch.'],
                    [self::SUP3, '2026-08-25 15:00', 'SMART data shows the SSD is failing (reallocated sectors rising). Replace the drive and reimage.', true],
                    [self::SUP3, '2026-08-27 11:20', 'Your laptop is ready with a new SSD and all your files restored. Please collect it from the IT desk.'],
                ],
            ],

            // ──────────────────────────── RESOLVED ────────────────────────────
            [
                'n' => 6, 'by' => 'employee6@example.com', 'category' => 'Software', 'priority' => P::Low, 'status' => S::Resolved,
                'title' => 'Adobe Creative Cloud licence not activating',
                'description' => 'Photoshop opens in trial mode and says my licence could not be verified. I need it for the campaign assets due Friday.',
                'created' => '2026-09-02 11:20', 'resolved' => '2026-09-03 15:00',
                'resolution' => 'Licence had been unassigned during a seat audit. Seat re-assigned and the client re-signed in successfully.',
                'assignments' => [[self::SUP2, self::ADMIN, '2026-09-02 11:45', null]],
                'comments' => [
                    [self::SUP2, '2026-09-02 12:00', 'Thanks Chamara, I am checking the licence portal for your seat.'],
                    [self::SUP2, '2026-09-03 14:40', 'Your seat was removed in the licence audit. I have re-assigned it — please sign out and in again in Creative Cloud.'],
                    ['employee6@example.com', '2026-09-03 14:55', 'Activated, thanks!'],
                ],
            ],
            [
                'n' => 7, 'by' => 'employee7@example.com', 'category' => 'Email', 'priority' => P::Medium, 'status' => S::Resolved,
                'title' => 'Outlook is not syncing emails',
                'description' => 'Outlook has shown "Disconnected" since yesterday afternoon. I can read mail in the browser but not in the desktop app, so I am missing calendar updates.',
                'created' => '2026-09-04 08:40', 'resolved' => '2026-09-04 14:10',
                'resolution' => 'Corrupted Outlook profile and OST file. Profile recreated and mailbox re-synchronised.',
                'assignments' => [[self::SUP1, self::ADMIN, '2026-09-04 09:00', null]],
                'comments' => [
                    ['employee7@example.com', '2026-09-04 08:42', 'I started experiencing this issue yesterday around 3 pm.'],
                    [self::SUP1, '2026-09-04 09:10', 'Thanks Sarah. Webmail working means the mailbox itself is fine. I will check your Outlook profile.'],
                    [self::SUP1, '2026-09-04 11:30', 'OST file is corrupt; Scanpst could not repair it. Recreating the profile.', true],
                    [self::SUP1, '2026-09-04 14:00', 'New profile is syncing and your mail and calendar are back. Please confirm everything looks right.'],
                ],
            ],
            [
                'n' => 8, 'by' => 'employee8@example.com', 'category' => 'Security', 'priority' => P::High, 'status' => S::Resolved,
                'title' => 'Antivirus warning appearing repeatedly',
                'description' => 'A red antivirus pop-up about a "potentially unwanted application" appears every few minutes on my laptop. I have not installed anything new.',
                'created' => '2026-09-08 09:05', 'resolved' => '2026-09-08 17:00',
                'resolution' => 'A browser extension flagged as a potentially unwanted application was removed. Full scan completed clean; extension policy tightened.',
                'assignments' => [[self::SUP3, self::ADMIN, '2026-09-08 09:15', 'Possible malware — prioritise.']],
                'comments' => [
                    [self::SUP3, '2026-09-08 09:30', 'Thanks Thomas. Please do not click anything in the pop-up. I will connect remotely to take a look.'],
                    [self::SUP3, '2026-09-08 11:00', 'PUA detected from a browser extension installed on 2026-09-01. Extension removed, full scan clean. No evidence of credential theft.', true],
                    [self::SUP3, '2026-09-08 16:50', 'The extension has been removed and a full scan is clean. If the warning returns, let me know immediately.'],
                ],
            ],
            [
                'n' => 9, 'by' => 'employee1@example.com', 'category' => 'System Issue', 'priority' => P::Critical, 'status' => S::Resolved,
                'title' => 'ERP system returns an error when posting invoices',
                'description' => 'Every attempt to post a supplier invoice in the ERP fails with "Error 500: transaction could not be completed". The whole Finance team is blocked and payments are due today.',
                'created' => '2026-09-09 13:15', 'resolved' => '2026-09-09 16:45',
                'resolution' => 'The ERP database transaction log volume was full. Log space extended and the application pool restarted; invoice posting verified by Finance.',
                'assignments' => [
                    [self::SUP2, self::ADMIN, '2026-09-09 13:20', 'Critical — whole team blocked.'],
                    [self::SUP1, self::ADMIN, '2026-09-09 13:50', 'Escalating to Ravi (ERP admin).'],
                ],
                'comments' => [
                    ['employee1@example.com', '2026-09-09 13:18', 'This is blocking all of Finance. Please treat as urgent.'],
                    [self::SUP2, '2026-09-09 13:30', 'Understood. Reproduced the error; escalating to our ERP administrator right now.'],
                    [self::SUP1, '2026-09-09 14:10', 'Root cause found: transaction log disk is at 100%. Extending the volume and restarting the app pool.', true],
                    [self::SUP1, '2026-09-09 16:30', 'The ERP is back. Please try posting an invoice and confirm.'],
                    ['employee1@example.com', '2026-09-09 16:40', 'Invoices are posting again. Thanks for the fast response.'],
                ],
            ],

            // ─────────────────────────── IN PROGRESS ──────────────────────────
            [
                'n' => 10, 'by' => 'employee2@example.com', 'category' => 'Software', 'priority' => P::Medium, 'status' => S::InProgress,
                'title' => 'Excel crashes when opening the large budget workbook',
                'description' => 'The FY27 budget workbook (about 80 MB) crashes Excel every time I open it. Colleagues with the same file have no problem.',
                'created' => '2026-09-15 09:30', 'resolved' => null,
                'assignments' => [[self::SUP1, self::ADMIN, '2026-09-15 09:50', null]],
                'comments' => [
                    [self::SUP1, '2026-09-15 10:15', 'Hi Daniel, I will check your Office installation and add-ins. Does it also crash in Safe Mode?'],
                    ['employee2@example.com', '2026-09-15 10:40', 'Yes, it also crashes when I start Excel in Safe Mode.'],
                    [self::SUP1, '2026-09-16 09:00', 'Office build is outdated and a repair did not help. Planning a clean reinstall of the 64-bit version.', true],
                ],
            ],
            [
                'n' => 11, 'by' => 'employee4@example.com', 'category' => 'Network', 'priority' => P::High, 'status' => S::InProgress,
                'title' => 'Wi-Fi disconnecting frequently in the Sales area',
                'description' => 'Wi-Fi drops every 10–15 minutes in the Sales open-plan area. Video calls with customers keep cutting out.',
                'created' => '2026-09-16 10:10', 'resolved' => null,
                'assignments' => [
                    [self::SUP3, self::ADMIN, '2026-09-16 10:30', null],
                    [self::SUP2, self::ADMIN, '2026-09-18 09:00', 'Moved to Emily — network infrastructure focus.'],
                ],
                'comments' => [
                    [self::SUP3, '2026-09-16 11:00', 'Thanks Kevin. Could you note the times the drops happen over the next day?'],
                    ['employee4@example.com', '2026-09-17 16:30', 'Drops at roughly 10:05, 11:40, 14:15 and 15:50 today.'],
                    [self::SUP2, '2026-09-18 11:20', 'Access point AP-S2-04 is running outdated firmware and reboots under load. Scheduling firmware update after hours.', true],
                    [self::SUP2, '2026-09-18 11:30', 'Hi Kevin, I have taken this over. We found a faulty access point and will update it this evening.'],
                ],
            ],
            [
                'n' => 12, 'by' => 'employee5@example.com', 'category' => 'Hardware', 'priority' => P::Medium, 'status' => S::InProgress,
                'title' => 'Monitor flickering on docking station',
                'description' => 'My external monitor flickers and goes black for a second every few minutes when connected through the docking station. Direct HDMI to the laptop is fine.',
                'created' => '2026-09-18 08:55', 'resolved' => null,
                'assignments' => [[self::SUP2, self::ADMIN, '2026-09-18 09:20', null]],
                'comments' => [
                    [self::SUP2, '2026-09-18 09:45', 'Hi Nadia, I will swap in a test dock and cable to isolate the problem.'],
                    [self::SUP2, '2026-09-21 10:00', 'Test dock works perfectly, so your dock is likely faulty. Replacement ordered.', true],
                    ['employee5@example.com', '2026-09-21 10:30', 'Thanks, in the meantime I will use the direct HDMI cable.'],
                ],
            ],
            [
                'n' => 13, 'by' => 'employee6@example.com', 'category' => 'Security', 'priority' => P::Critical, 'status' => S::InProgress,
                'title' => 'Suspicious phishing email clicked by a team member',
                'description' => 'A colleague in Marketing clicked a link in an email that looked like a shared-document invite and entered her password on the page. Two other people received the same email.',
                'created' => '2026-09-22 15:20', 'resolved' => null,
                'assignments' => [[self::SUP1, self::ADMIN, '2026-09-22 15:25', 'Security incident — handle immediately.']],
                'comments' => [
                    [self::SUP1, '2026-09-22 15:30', 'Thanks for reporting quickly. Please ask her not to use that account until we call her. I am forcing a password reset and revoking active sessions now.'],
                    [self::SUP1, '2026-09-22 15:50', 'Message headers show a spoofed sender from a lookalike domain. Credentials reset, sessions revoked. Blocking the domain at the mail gateway and searching all mailboxes for the message.', true],
                    ['employee6@example.com', '2026-09-22 16:10', 'She has been told. The other two people did not click the link.'],
                    [self::SUP1, '2026-09-23 09:30', 'Sign-in logs show no suspicious logins after the reset. Still reviewing mailbox rules for forwarding.', true],
                ],
            ],
            [
                'n' => 14, 'by' => 'employee7@example.com', 'category' => 'Network', 'priority' => P::Critical, 'status' => S::InProgress,
                'title' => 'Warehouse site has no internet connectivity',
                'description' => 'The warehouse has had no internet since 06:30. Scanners cannot sync and we cannot print dispatch notes. About 25 staff are affected.',
                'created' => '2026-09-25 06:40', 'resolved' => null,
                'assignments' => [
                    [self::SUP2, self::ADMIN, '2026-09-25 06:50', 'Site outage — critical.'],
                    [self::SUP3, self::SUP2, '2026-09-25 09:30', 'Engineer visit needed on site; Marcus is closest.'],
                ],
                'comments' => [
                    [self::SUP2, '2026-09-25 07:00', 'Understood Sarah. The router is not responding to remote checks, so I am contacting the ISP.'],
                    [self::SUP2, '2026-09-25 08:15', 'ISP confirms a fibre cut in the area. ETA for repair is 4 hours; raising a ticket with the ISP.', true],
                    [self::SUP3, '2026-09-25 09:45', 'Hi Sarah, I am taking over and heading to the site. I will set up the 4G backup router so you can print and sync until the fibre is restored.'],
                    ['employee7@example.com', '2026-09-25 10:10', 'Thank you. Please hurry, the dispatch queue is growing.'],
                ],
            ],

            // ─────────────────────── WAITING FOR USER ─────────────────────────
            [
                'n' => 15, 'by' => 'employee3@example.com', 'category' => 'Email', 'priority' => P::Low, 'status' => S::WaitingForUser,
                'title' => 'Shared HR mailbox not visible in Outlook',
                'description' => 'The shared mailbox hr-requests@example.com no longer appears in my Outlook folder list. I was able to see it last week.',
                'created' => '2026-09-23 11:00', 'resolved' => null,
                'assignments' => [[self::SUP2, self::ADMIN, '2026-09-23 11:30', null]],
                'comments' => [
                    [self::SUP2, '2026-09-23 12:00', 'Hi Amira, your permissions on the mailbox look correct. Could you send a screenshot of File > Account Settings > Account Settings?'],
                    [self::SUP2, '2026-09-24 09:00', 'Waiting for the screenshot before changing anything on the mailbox.', true],
                ],
            ],
            [
                'n' => 16, 'by' => 'employee8@example.com', 'category' => 'Software', 'priority' => P::Medium, 'status' => S::WaitingForUser,
                'title' => 'Microsoft Teams microphone not working',
                'description' => 'In Teams meetings other participants cannot hear me. My microphone works in other apps.',
                'created' => '2026-09-24 09:45', 'resolved' => null,
                'assignments' => [[self::SUP3, self::ADMIN, '2026-09-24 10:05', null]],
                'comments' => [
                    [self::SUP3, '2026-09-24 10:20', 'Thanks Thomas. I have checked the device permissions and updated the Teams client. Please run a test call with your headset plugged in and tell me the result.'],
                    ['employee8@example.com', '2026-09-24 10:35', 'I am travelling today. I will test it tomorrow morning.'],
                ],
            ],
            [
                'n' => 17, 'by' => 'employee1@example.com', 'category' => 'Account & Access', 'priority' => P::Medium, 'status' => S::WaitingForUser,
                'title' => 'Password reset required for the finance portal',
                'description' => 'I am locked out of the supplier finance portal after too many attempts and the self-service reset e-mail never arrives.',
                'created' => '2026-09-26 08:20', 'resolved' => null,
                'assignments' => [[self::SUP1, self::ADMIN, '2026-09-26 08:40', null]],
                'comments' => [
                    [self::SUP1, '2026-09-26 09:00', 'Hi Priya. For security I need to verify your identity first. Please call the IT desk on extension 100 and quote this ticket number.'],
                    [self::SUP1, '2026-09-26 09:05', 'Reset e-mails to this user are bouncing from the portal vendor — check spam filter allow-list once identity is verified.', true],
                ],
            ],

            // ───────────────────────────── ASSIGNED ───────────────────────────
            [
                'n' => 18, 'by' => 'employee2@example.com', 'category' => 'Hardware', 'priority' => P::High, 'status' => S::Assigned,
                'title' => 'Laptop battery swelling and keyboard lifting',
                'description' => 'The battery in my laptop seems to be swelling: the keyboard is lifting and the trackpad is hard to click. I have unplugged it and stopped using it.',
                'created' => '2026-09-28 10:30', 'resolved' => null,
                'assignments' => [[self::SUP2, self::ADMIN, '2026-09-28 10:45', 'Safety risk — replace laptop today.']],
                'comments' => [
                    [self::SUP2, '2026-09-28 11:00', 'Please keep the laptop unplugged and away from heat. I will bring you a loan laptop this afternoon.'],
                    ['employee2@example.com', '2026-09-28 11:10', 'Thank you, I have moved it away from my desk.'],
                ],
            ],
            [
                'n' => 19, 'by' => 'employee3@example.com', 'category' => 'Software', 'priority' => P::Low, 'status' => S::Assigned,
                'title' => 'Request to install Zoom for external interviews',
                'description' => 'We are interviewing candidates from a partner agency who only use Zoom. Please install Zoom on my laptop for the next two weeks.',
                'created' => '2026-09-28 14:00', 'resolved' => null,
                'assignments' => [[self::SUP3, self::ADMIN, '2026-09-28 14:20', null]],
                'comments' => [
                    [self::SUP3, '2026-09-28 14:30', 'Hi Amira, Zoom is on the approved software list. I will push it to your laptop tonight.'],
                ],
            ],
            [
                'n' => 20, 'by' => 'employee5@example.com', 'category' => 'Printer', 'priority' => P::Medium, 'status' => S::Assigned,
                'title' => 'Printer prints blank pages in Sales',
                'description' => 'The Sales department colour printer prints completely blank pages. The toner level shows full.',
                'created' => '2026-09-29 09:10', 'resolved' => null,
                'assignments' => [
                    [self::SUP1, self::ADMIN, '2026-09-29 09:30', null],
                    [self::SUP3, self::SUP1, '2026-09-29 09:50', 'Printers are handled by Marcus this week.'],
                ],
                'comments' => [
                    [self::SUP3, '2026-09-29 10:15', 'Hi Nadia, I will check the printer on site later today. Please do not power-cycle it before I arrive.'],
                    [self::SUP3, '2026-09-29 10:20', 'Likely imaging drum or protective film left on a new toner cartridge. Check on arrival.', true],
                ],
            ],
            [
                'n' => 21, 'by' => 'employee7@example.com', 'category' => 'System Issue', 'priority' => P::High, 'status' => S::Assigned,
                'title' => 'Inventory scanner app fails to sync with the server',
                'description' => 'The handheld inventory scanners show "Sync failed (timeout)" and stock counts are not reaching the inventory system.',
                'created' => '2026-09-29 13:25', 'resolved' => null,
                'assignments' => [[self::SUP2, self::ADMIN, '2026-09-29 13:50', null]],
                'comments' => [
                    [self::SUP2, '2026-09-29 14:05', 'Thanks Sarah. Do all scanners fail, or only some? Please let me know which ones.'],
                    ['employee7@example.com', '2026-09-29 14:30', 'All four scanners in the main warehouse fail.'],
                ],
            ],

            // ────────────────────────────── OPEN ──────────────────────────────
            [
                'n' => 22, 'by' => 'employee6@example.com', 'category' => 'Other', 'priority' => P::Low, 'status' => S::Open,
                'title' => 'Request for a second monitor for design work',
                'description' => 'I would like a second 27-inch monitor for my desk. My manager has approved the request for the design workload.',
                'created' => '2026-09-29 16:00', 'resolved' => null,
                'assignments' => [], 'comments' => [],
            ],
            [
                'n' => 23, 'by' => 'employee4@example.com', 'category' => 'Email', 'priority' => P::High, 'status' => S::Open,
                'title' => 'Unable to send emails to external customers',
                'description' => 'Emails to customers at external domains bounce with "550 5.7.1 message rejected" while internal email works. This started this morning.',
                'created' => '2026-09-30 08:15', 'resolved' => null,
                'assignments' => [],
                'comments' => [
                    ['employee4@example.com', '2026-09-30 08:40', 'This is urgent: three customers are waiting for quotations I cannot send.'],
                ],
            ],
            [
                'n' => 24, 'by' => 'employee8@example.com', 'category' => 'Account & Access', 'priority' => P::Medium, 'status' => S::Open,
                'title' => 'Need access to the board reporting SharePoint site',
                'description' => 'I need read access to the Board Reporting SharePoint site for the Q3 review. My manager (CEO) approves.',
                'created' => '2026-09-30 09:20', 'resolved' => null,
                'assignments' => [], 'comments' => [],
            ],
            [
                'n' => 25, 'by' => 'employee2@example.com', 'category' => 'Hardware', 'priority' => P::Low, 'status' => S::Open,
                'title' => 'Keyboard keys sticking on desktop workstation',
                'description' => 'Several keys (E, R and the space bar) stick on my desktop keyboard after a coffee spill last week.',
                'created' => '2026-09-30 10:05', 'resolved' => null,
                'assignments' => [], 'comments' => [],
            ],
            [
                'n' => 26, 'by' => 'employee7@example.com', 'category' => 'Network', 'priority' => P::Medium, 'status' => S::Open,
                'title' => 'Slow file transfers to the shared drive',
                'description' => 'Copying files to the Operations shared drive is extremely slow (about 1 MB/s) compared with last month. Opening large PDFs from the drive also hangs.',
                'created' => '2026-09-30 11:30', 'resolved' => null,
                'assignments' => [],
                'comments' => [
                    ['employee7@example.com', '2026-09-30 11:35', 'It is slow for everybody in Operations, not only me.'],
                ],
            ],
        ];
    }
}
