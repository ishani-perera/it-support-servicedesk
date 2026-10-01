<?php

namespace App\Policies;

/**
 * Read: any active user. Write: Admin only (see MasterDataPolicy).
 */
class TicketStatusPolicy extends MasterDataPolicy {}
