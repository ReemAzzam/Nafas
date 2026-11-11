<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReminderPreference extends Model {
 protected $fillable = ['user_id','enabled','time','timezone'];
 protected $casts = ['enabled'=>'boolean'];
 protected $hidden = ['user_id'];
}
