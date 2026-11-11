<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudyPlan extends Model {
 protected $fillable = ['user_id', 'payload'];
 protected $casts = ['payload'=>'encrypted:array'];
 protected $hidden = ['user_id'];
}
