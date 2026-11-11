<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportMessage extends Model
{
    protected $fillable = ['user_id', 'role', 'content', 'sources', 'urgent', 'request_key', 'mode', 'request_fingerprint'];
    protected $casts = ['content' => 'encrypted', 'sources' => 'array', 'urgent' => 'boolean'];
    protected $hidden = ['user_id', 'request_key', 'request_fingerprint'];
}
