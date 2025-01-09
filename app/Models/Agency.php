<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\User
 *
 * @property int $id
 * @property int $main_admin_id
 * @property string $name
 * @property string $messagetext
 * @property string $status
 * @property int $delete_images
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class Agency extends Model
{
    use HasFactory;

    const STATUS = [
        'active' => 'active',
        'not_active' => 'not_active',
        'deleted' => 'deleted',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'main_admin_id',
        'name',
        'status',
        'delete_images',
    ];


    public function admin()
    {
        return $this->belongsTo(User::class, 'main_admin_id', 'id');
    }

    public function members()
    {
        return $this->hasMany(AgencyMember::class, 'agency_id', 'id');
    }

    public static function statusList() {
        return [
            self::STATUS['active'] => 'Active',
            self::STATUS['not_active'] => 'Not active',
            self::STATUS['deleted'] => 'Deleted',
        ];
    }
}
