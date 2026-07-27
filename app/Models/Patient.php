<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'name',
        'age',
        'address',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
