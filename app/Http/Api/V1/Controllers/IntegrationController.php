<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Resources\IntegrationCollection;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\Task;
use App\Models\User;
use App\Models\UserDomain;
use App\Models\UserGscStat;
use App\Models\UserIntegration;
use App\Service\AuthAssistService;
use App\Service\GoogleSearchConsoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class IntegrationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return IntegrationCollection|JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = $this->getUserQuery();

            $integrations = $query
                ->get();

            return new IntegrationCollection($integrations);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = \Auth::user();
            $validator = Validator::make($request->all(), [
                'type' => [
                    'required',
                    //'in:google_search_console',
                    Rule::unique('user_integrations', 'type')->where('user_id', $user->id),
                ],
                'api_key' => 'nullable|string|max:512',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            $params = [];
            if ($request->api_key) {
                $params['api_key_hash'] =  (new AuthAssistService($request))->securedEncrypt($user, $request->api_key);
            }

            UserIntegration::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'params' => $params,
                'status' => UserIntegration::STATUS['notConnected'],
            ]);

            return $this->sendResponse([], "Integration has been created successfully");
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return IntegrationCollection|JsonResponse
     */
    public function show($id)
    {
        try {
            $query = $this->getUserQuery();

            $integration = $query
                ->where('id', $id)
                ->first();

            if (!$integration) {
                throw new \Exception('The integration is not found', 404);
            }

            return new IntegrationCollection([$integration]);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            if (strlen($e->getCode()) == 3) {
                return $this->sendError('Error: ' . $e->getMessage(), [], $e->getCode());
            }
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return mixed
     */
    public function edit($id)
    {
        try {
            $query = $this->getUserQuery();

            $integration = $query
                ->where('id', $id)
                ->first();

            if (!$integration) {
                throw new \Exception('The integration is not found', 404);
            }

            return new IntegrationCollection([$integration]);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            if (strlen($e->getCode()) == 3) {
                return $this->sendError('Error: ' . $e->getMessage(), [], $e->getCode());
            }
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $query = $this->getUserQuery();

            $integration = $query
                ->where('id', $id)
                ->first();

            if (!$integration) {
                throw new \Exception('The integration is not found', 404);
            }
            $integrationsOwners = $this->integrationsOwners();

            $validator = Validator::make($request->all(), [
                'type' => [
                    'required',
                    //'in:google_search_console',
                    Rule::unique('user_integrations', 'type')->whereIn('user_id', $integrationsOwners)->ignore($id),
                ],
                'api_key' => 'required|string|max:512',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }

            $integration->update([
                'api_key_hash' => (new AuthAssistService($request))->securedEncrypt($integration->user_id, $request->api_key),
                'status' => $request->status,
            ]);


            return $this->sendResponse([], "Integration {$integration->id} has been updated successfully");
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            if (strlen($e->getCode()) == 3) {
                return $this->sendError('Error: ' . $e->getMessage(), [], $e->getCode());
            }
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $query = $this->getUserQuery();

            $integration = $query
                ->where('id', $id)
                ->first();

            if (!$integration) {
                throw new \Exception('The integration is not found', 404);
            }
            $integration->delete();

            return $this->sendResponse([], "Integration {$integration->id} has been deleted successfully");
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            if (strlen($e->getCode()) == 3) {
                return $this->sendError('Error: ' . $e->getMessage(), [], $e->getCode());
            }
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Get GSC statistic.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gscStat(Request $request): JsonResponse {
        try {
            $user = \Auth::user();
            $validator = Validator::make($request->all(), [
                'period' => 'in:' . implode(',', UserGscStat::PERIOD),
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }

            $stat = UserGscStat::stat($user->id, $request->period);

            return $this->sendResponse($stat);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Connect GSC
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gscCheckConnection(Request $request): JsonResponse {
        try {
            $user = \Auth::user();
            $validator = Validator::make($request->all(), [
                'gsca' => 'string|max:512',
                'gscr' => 'string|max:512',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            Log::info(1);

            $message = '';
            $integration = UserIntegration::where('user_id', $user->id)
                ->where('type', UserIntegration::TYPE['google_search_console'])
                ->first();
            if ($integration) {
                if ($integration->status == UserIntegration::STATUS['connected']) {
                    $message = 'GSC integration already is connected';
                }
            } else {
                $integration = new UserIntegration();
                $integration->user_id = $user->id;
                $integration->type = UserIntegration::TYPE['google_search_console'];
            }
            if (!$message && !$request->gsca) {
                throw new \Exception('GSC integration is not connected');
            }
            if (!$message) {
                $integration->params = [
                    'gsca' => $request->gsca,
                    'gscr' => str_replace('\\', '', $request->gscr),
                ];
                $integration->save();

                $gscService = new GoogleSearchConsoleService($request, $integration);
                $res = $gscService->checkConnection();
                if (!$res) {
                    throw new \Exception('Connection is not successfull');
                }

                $integration->status = UserIntegration::STATUS['connected'];
                $integration->save();

                Task::add($user->id, Task::TYPES['update_gsc_stat']);
                $message = 'You have successfully connected GSC integration';

                $domains = UserDomain::where('user_id', $user->id)
                    ->where('status', UserDomain::STATUS['active'])
                    ->get();

                if ($domains) {
                    $pages = $gscService->siteList();
                    if ($pages) {
                        foreach ($pages as $page) {
                            if ($page->permissionLevel == 'siteUnverifiedUser') {
                                continue;
                            }
                            foreach ($domains as $domain) {
                                if (strpos($page->siteUrl, $domain->name) !== false) {
                                    $params = [];
                                    $params['startDate'] = ((new \DateTime())->sub(new \DateInterval('P1D')))->format('Y-m-d');
                                    $params['endDate'] = date('Y-m-d');
                                    if (!UserGscStat::where('user_id', $user->id)->exists()) {
                                        $params['startDate'] = date('2010-01-01');
                                    }
                                    $params['dimensions'] = ['country', 'device'];
                                    $rows = $gscService->querySearchAnalitic($page->siteUrl, $params);
                                    if ($rows) {
                                        foreach ($rows as $row) {
                                            UserGscStat::addRow($user->id, $params['endDate'], $page->siteUrl, $domain, $row);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $this->sendResponse([], $message);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Get GSC Access Token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function gscToken(Request $request) {
        try {
            $gscService = new GoogleSearchConsoleService($request);

            $creds = $gscService->getToken();
            if (!$creds) {
                throw new \Exception('Failed to get token');
            }
            $redirectUrl = env('GOOGLE_SEARCH_CONSOLE_REDIRECT_URL');
            if (!$redirectUrl) {
                throw new \Exception('Set redirect url');
            }

            return redirect($redirectUrl . '?accessToken='. $creds['access_token'] . '&refreshToken=' . $creds['refresh_token'] . '&state=' . $request->state);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
        }
    }

    /**
     * Get user query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
    */
    protected function getUserQuery(): Builder {
        $user = Auth::user();
        $query = UserIntegration::query();
        if (User::isAgencyMember($user)) {
            $query
                ->whereIn(
                    'user_id',
                    DB::table('users')
                        ->select('users.id')
                        ->join('agency_members', 'users.id', '=', 'agency_members.user_id')
                        ->join('agencies', 'agency_members.agency_id', '=', 'agencies.id')
                        ->where('agency_members.status', AgencyMember::STATUS['active'])
                        ->where('agencies.id', $user->agency->id)
                        ->where('agencies.status', Agency::STATUS['active'])
                );
        } elseif ($user->role != User::SUPERADMIN) {
            $query
                ->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * Get integrations owners list
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function integrationsOwners(): array {
        $user = Auth::user();
        $query = User::query();
        if (User::isAgencyMember($user)) {
            $query
                ->whereIn(
                    'id',
                    DB::table('users')
                        ->select('users.id')
                        ->join('agency_members', 'users.id', '=', 'agency_members.user_id')
                        ->join('agencies', 'agency_members.agency_id', '=', 'agencies.id')
                        ->where('agency_members.status', AgencyMember::STATUS['active'])
                        ->where('agencies.id', $user->agency->id)
                        ->where('agencies.status', Agency::STATUS['active'])
                );
        } else {
            $query
                ->where('id', $user->id);
        }

        return $query
            ->pluck('id')
            ->toArray();
    }
}
