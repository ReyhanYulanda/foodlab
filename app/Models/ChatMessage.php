<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';

    protected $casts = [
        'transaksi_id' => 'integer',
        'sender_id' => 'integer',
    ];

    protected $fillable = [
        'transaksi_id',
        'sender_id',
        'sender_name',
        'message',
        'chat_type',
    ];

    protected $appends = [
        'sender_image',
    ];

    public function getSenderImageAttribute()
    {
        $user = User::find($this->sender_id);
        return $user ? $user->image : null;
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id', 'id');
    }
}
