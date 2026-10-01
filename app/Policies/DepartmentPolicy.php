<?php

namespace App\Policies;

/**
 * Read: any active user. Write: Admin only (see MasterDataPolicy).
 */
class DepartmentPolicy extends MasterDataPolicy {}
