<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives in each organisation's own database.
 *
 * @property int $id
 * @property string $customer_name
 * @property string $postcode
 * @property int|null $heat_loss_watts
 */
class Project extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['customer_name', 'postcode', 'heat_loss_watts'];
}
