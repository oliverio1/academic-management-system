<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'body',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

