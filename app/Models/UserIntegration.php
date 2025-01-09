<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use function PHPUnit\Framework\isJson;

/**
 * App\Models\UserIntegration
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $params
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $data_updated_at
 * @mixin \Eloquent
 */
class UserIntegration extends Model
{
    use HasFactory;

    const TYPE = [
        'google_search_console' => 'google_search_console',
    ];

    const STATUS = [
        'notConnected' => 0,
        'connected' => 1,
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::retrieved(function ($model) {
            $model->params = json_decode($model->params, true);
        });
        static::creating(function ($model) {
            if (!self::isJson($model->params)) {
                $model->params = json_encode($model->params);
            }
        });
        static::saved(function ($model) {
            if (self::isJson($model->params)) {
                $model->params = json_decode($model->params, true);
            }
        });
        static::updating(function ($model) {
            if (!self::isJson($model->params)) {
                $model->params = json_encode($model->params);
            }
        });
    }

    protected static function isJson($params) {
        if (is_array($params)) {
            return false;
        }
        json_decode($params);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
