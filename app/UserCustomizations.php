<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserCustomizations extends Model
{
    protected $table = 'user_customizations';

    protected $fillable = [
        'user_id', 'customization'
    ];
}
