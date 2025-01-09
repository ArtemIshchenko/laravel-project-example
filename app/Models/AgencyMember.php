<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\AgencyMember
 *
 * @property int $id
 * @property int $agency_id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $invited_at
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class AgencyMember extends Model
{
    use HasFactory;

    const STATUS = [
        'active' => 'active',
        'invite' => 'invite',
        'waiting_approve' => 'waiting_approve',
        'not_active' => 'not_active',
        'deleted' => 'deleted',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'agency_id',
        'user_id',
        'created_by',
        'status',
        'invited_at',
        'approved_at',
    ];


    public function admin()
    {
        return $this->belongsTo(User::class, 'main_admin_id', 'id');
    }

    public static function statusList() {
        return [
            self::STATUS['active'] => 'Active',
            self::STATUS['invite'] => 'Invite',
            self::STATUS['not_active'] => 'Not active',
            self::STATUS['deleted'] => 'Deleted',
        ];
    }

    public static function isInAgency() {
        return self::where('user_id', \Auth::user()->id)
            ->where('status', self::STATUS['active'])
            ->exists();
    }

    public static function isAgencyProject($projectId, $agencyId) {
        return DB::table('web_templates')
            ->join('agency_members', 'web_templates.user_id', '=', 'agency_members.user_id')
            ->join('agencies', 'agency_members.agency_id', '=', 'agencies.id')
            ->where('web_templates.id', $projectId)
            ->where('agency_members.status', AgencyMember::STATUS['active'])
            ->where('agencies.id', $agencyId)
            ->where('agencies.status', Agency::STATUS['active'])
            ->exists();
    }

    public static function isAgencyImagesProject($projectId, $agencyId) {
        return DB::table('web_images')
            ->join('agency_members', 'web_images.user_id', '=', 'agency_members.user_id')
            ->join('agencies', 'agency_members.agency_id', '=', 'agencies.id')
            ->where('web_images.id', $projectId)
            ->where('agency_members.status', AgencyMember::STATUS['active'])
            ->where('agencies.id', $agencyId)
            ->where('agencies.status', Agency::STATUS['active'])
            ->exists();
    }

    public static function mainAdminList($currentAgency = null) {
        $subQuery = DB::table('users as u')
            ->select(['u.id'])
            ->join('agency_members as am', 'u.id', '=', 'am.user_id')
            ->whereIn('am.status', [AgencyMember::STATUS['active'], AgencyMember::STATUS['invite']]);
        if ($currentAgency) {
            $subQuery->where('u.id', '<>', $currentAgency->main_admin_id);
        }
        $users = DB::table('users')
            ->select(['users.id as id', 'email', 'name', 'first_name', 'last_name'])
            ->where('status', User::ACTIVE)
            ->whereNotExists(function (\Illuminate\Database\Query\Builder $query) use($currentAgency) {
                $query
                    ->select(DB::raw(1))
                    ->from('agencies')
                    ->whereColumn('main_admin_id', 'users.id');
                if ($currentAgency) {
                    $query->where('main_admin_id', '<>', $currentAgency->main_admin_id);
                }
                $query->whereIn('status', [Agency::STATUS['active']]);
            })
            ->whereNotIn('users.id',
                $subQuery
            )
            ->orderByDesc('users.created_at')
            ->get();

        foreach($users as $k => $user) {
            if (!empty($users[$k]->first_name) || !empty($users[$k]->last_name)) {
                $users[$k]->name = $users[$k]->first_name . ' ' . $users[$k]->last_name;
            }
        }


        return $users;
    }
}
