<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;

class SessionManager extends Model {

    protected $table='lp_ussd_session_manager';
}
