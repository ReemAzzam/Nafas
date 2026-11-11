<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecommendationFeedback extends Model
{
    protected $table = 'recommendation_feedback';
    protected $fillable = ['user_id', 'item_type', 'item_id', 'rating'];
    protected $casts = ['rating'=>'integer', 'item_id'=>'integer'];
}
