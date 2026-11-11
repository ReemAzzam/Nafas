<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'icon'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_goals')->withTimestamps();
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'goal_exercise')->withTimestamps();
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_goal')->withTimestamps();
    }
}
