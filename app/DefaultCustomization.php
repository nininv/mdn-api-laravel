<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DefaultCustomization extends Model
{
    protected $table = 'default_customizations';

    protected $fillable = [
        'machine_id',
        'customization'
    ];
}
