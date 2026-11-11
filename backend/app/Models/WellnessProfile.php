<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WellnessProfile extends Model
{
    protected $fillable = ['user_id', 'snapshot'];
    protected $casts = ['snapshot' => 'encrypted:array'];
}
