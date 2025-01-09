<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\UserGscStat
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int $url
 * @property int $impressions_count
 * @property int $click_count
 * @property int $keywords_count
 * @property int $position
 * @property \Illuminate\Support\Carbon $date
 * @mixin \Eloquent
 */
class UserGscStat extends Model
{
    use HasFactory;

    const PERIOD = [
        '1d' => '1d',
        '3d' => '3d',
        '7d' => '7d',
        '14d' => '14d',
        '30d' => '30d',
        '2m' => '2m',
        '6m' => '6m',
        '1y' => '1y',
    ];

    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public static function addRow($userId, $endDate, $siteUrl, $domain, $row) {
        $stat = self::where('user_id', $userId)
                ->where('date', $endDate)
                ->first();

        $projectKeywordsCount = WebTemplates::where('user_id', $userId)
            ->where('domain_id', $domain->id)
            ->where('status', WebTemplates::WORKING)
            ->sum('keywords_count');

        if ($stat) {
            $stat->impressions_count = $row['impressions'];
            $stat->click_count = $row['clicks'];
            $stat->position = $row['position'];

        } else {
            $stat = new self;
            $stat->user_id = $userId;
            $stat->url = $siteUrl;
            $stat->impressions_count = $row['impressions'];
            $stat->click_count = $row['clicks'];
            $stat->keywords_count = $projectKeywordsCount;
            $stat->position = $row['position'];
            $stat->date = $endDate;

        }

        return $stat->save();
    }

    public static function stat($userId, $period = self::PERIOD['1d']) {
        $currentTime = (new \DateTime())->format('Y-m-d');
        $curPeriodQuery = self::where('user_id', $userId);
        $prevPeriodQuery = clone $curPeriodQuery;
        $curPeriodKeywordsQuery = clone $curPeriodQuery;;
        $prevPeriodKeywordsQuery = clone $curPeriodQuery;;

        switch($period) {
            case self::PERIOD['1d']:
                $curPeriodQuery->where('date', $currentTime);
                $prevPeriodQuery->where('date', ((new \DateTime())->sub(new \DateInterval('P1D')))->format('Y-m-d'));
                $curPeriodKeywordsQuery->where('date', $currentTime);
                $prevPeriodKeywordsQuery->where('date', ((new \DateTime())->sub(new \DateInterval('P1D')))->format('Y-m-d'));
                break;
            case self::PERIOD['3d']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P3D')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P3D')))->format('Y-m-d')]);
                break;
            case self::PERIOD['7d']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P14D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P7D')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P14D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P7D')))->format('Y-m-d')]);
                break;
            case self::PERIOD['14d']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P13D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P28D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P14D')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P13D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P28D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P14D')))->format('Y-m-d')]);
                break;
            case self::PERIOD['30d']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P29D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P60D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P30D')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P29D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P60D')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P30D')))->format('Y-m-d')]);
                break;
            case self::PERIOD['2m']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2M1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P4M')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P2M')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2M1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P4M')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P2M')))->format('Y-m-d')]);
                break;
            case self::PERIOD['6m']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6M1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P12M')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P6M')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P6M1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P12M')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P6M')))->format('Y-m-d')]);
                break;
            case self::PERIOD['1y']:
                $curPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P1Y1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2Y')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P1Y')))->format('Y-m-d')]);
                $curPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P1Y1D')))->format('Y-m-d'), $currentTime]);
                $prevPeriodKeywordsQuery->whereBetween('date', [((new \DateTime())->sub(new \DateInterval('P2Y')))->format('Y-m-d'), ((new \DateTime())->sub(new \DateInterval('P1Y')))->format('Y-m-d')]);
                break;
        }

        $positionCur = (clone $curPeriodQuery)->avg('position');
        $positionPrev = (clone $prevPeriodQuery)->avg('position');

        return [
            'curPeriod' => [
                'impressionsCount' => (int) $curPeriodQuery->sum('impressions_count'),
                'clickCount' => (int) (clone $curPeriodQuery)->sum('click_count'),
                'position' => $positionCur ? round($positionCur) : 0,
                'keywordsCount' => (int) $curPeriodKeywordsQuery->sum('user_gsc_stats.keywords_count'),
            ],
            'prevPeriod' => [
                'impressionsCount' => (int) $prevPeriodQuery->sum('impressions_count'),
                'clickCount' => (int) (clone $prevPeriodQuery)->sum('click_count'),
                'position' => $positionPrev ? round($positionPrev) : 0,
                'keywordsCount' => (int) $prevPeriodKeywordsQuery->sum('user_gsc_stats.keywords_count'),
            ]
        ];
    }
}
