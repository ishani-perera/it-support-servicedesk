<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class TicketAttachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'comment_id',
        'uploaded_by',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /**
     * `file_path` and the server-generated `file_name` reveal the internal storage
     * layout and must never appear in serialised/public output; files are served
     * through an authorised download route. `original_name` is the
     * display name.
     *
     * @var list<string>
     */
    protected $hidden = [
        'file_path',
        'file_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * `file_size` is stored (and cast) as an integer number of BYTES everywhere.
     * This accessor is for display only: 1536 => "1 KB".
     */
    protected function sizeForHumans(): Attribute
    {
        return Attribute::get(fn (): string => Number::fileSize($this->file_size, precision: 1));
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The comment this file belongs to; null for ticket-level attachments.
     */
    /**
     * @return BelongsTo<TicketComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
