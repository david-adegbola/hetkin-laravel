<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read/delete access to Laravel's own `sessions` table (SESSION_DRIVER=
 * database) — not a table this app owns the shape of, so no migration
 * lives alongside this model; it exists purely so the Account Security
 * module's session-management feature (list/revoke active sessions) can
 * query it through Eloquent instead of the raw query builder everywhere.
 *
 * Deliberately named `SessionRecord`, not `Session` — `Session` is
 * Laravel's own facade name, and shadowing it in application code invites
 * exactly the kind of confusion a security-sensitive module shouldn't have.
 */
class SessionRecord extends Model
{
    protected $table = 'sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [];

    public function lastActivityAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->last_activity);
    }
}
