<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Resources\AgencyCollection;
use App\Http\Resources\AgencyMemberCollection;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class AgencyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return AgencyCollection|JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer',
                'status' => 'nullable|in:' . implode(',', Agency::STATUS),
                'filter' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            $rowPerPage = $request->per_page ?? config('view.row_per_page');

            $query = Agency::with('admin')->orderBy('created_at');
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->filter) {
                $query
                    ->whereHas('admin', function (Builder $q) use ($request) {
                        $q->where('email', 'like', '%' . $request->filter . '%')
                            ->orWhere('name', 'like', '%' . $request->filter . '%')
                            ->orWhere('first_name', 'like', '%' . $request->filter . '%')
                            ->orWhere('last_name', 'like', '%' . $request->filter . '%')
                            ->orWhere('agencies.name', 'like', $request->filter . '%');
                    });
            }
            $agencies = $query->paginate($rowPerPage);

            return new AgencyCollection($agencies);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     * @return JsonResponse
     */
    public function create(): JsonResponse
    {
        try {
            return $this->sendResponse(AgencyMember::mainAdminList());
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
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
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:128',
                'main_admin_id' => 'required|integer',
                'status' => 'string|in:' . implode(',', Agency::STATUS),
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }

            $agency = Agency::create([
                'name' => $request->name,
                'main_admin_id' => $request->main_admin_id,
                'status' => $request->status ?? Agency::STATUS['active'],
            ]);
            if ($agency) {
                AgencyMember::create([
                    'agency_id' => $agency->id,
                    'user_id' => $agency->main_admin_id,
                    'status' => AgencyMember::STATUS['active'],
                    'created_by' => $agency->main_admin_id,
                    'invited_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return $this->sendResponse([], "Agency {$request->name} has been created successfully");
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return AgencyCollection|JsonResponse
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return AgencyCollection|JsonResponse
     */
    public function edit($id)
    {
        try {
           $agency = Agency::find($id);
            if (!$agency) {
                throw new \Exception('Agency with ID=' . $id . ' is not found', 404);
            }

            return (new AgencyCollection([$agency]))->additional([
                'main_admin_list' => AgencyMember::mainAdminList($agency),
            ]);
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
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:128',
                'main_admin_id' => 'required|integer',
                'status' => 'string|in:' . implode(',', Agency::STATUS),
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            $agency = Agency::find($id);
            if (!$agency) {
                throw new \Exception('Agency with ID=' . $id . ' is not found', 404);
            }
            $oldMainAdminId = $agency->main_admin_id;
            if ($agency->update([
                'name' => $request->name,
                'main_admin_id' => $request->main_admin_id,
                'status' => $request->status ?? Agency::STATUS['active'],
            ])) {
                if ($request->status == Agency::STATUS['deleted']) {
                    AgencyMember::where([
                        'agency_id' => $id,
                    ])
                        ->delete();
                } else {
                    $agencyMember = AgencyMember::where('agency_id', $agency->id)->where('user_id', $oldMainAdminId)->first();
                    if ($agencyMember) {
                        if ($agency->main_admin_id !== $oldMainAdminId) {
                            $agencyMember->update(['user_id' => $agency->main_admin_id]);
                        }
                    } else {
                        AgencyMember::create([
                            'agency_id' => $agency->id,
                            'user_id' => $agency->main_admin_id,
                            'status' => AgencyMember::STATUS['active'],
                            'created_by' => $agency->main_admin_id,
                            'invited_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            return $this->sendResponse([], "Agency {$request->name} has been updated successfully");
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
            $agency = Agency::find($id);
            if (!$agency) {
                throw new \Exception('Agency with ID=' . $id . ' is not found', 404);
            }
           if (AgencyMember::where(['agency_id' => $id])->delete()) {
                Agency::findOrFail($id)
                    ->update(['status' => Agency::STATUS['deleted']]);
            }

            return $this->sendResponse([], "Agency {$agency->name} has been deleted successfully");
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            if (strlen($e->getCode()) == 3) {
                return $this->sendError('Error: ' . $e->getMessage(), [], $e->getCode());
            }
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }


    /**
     * Agency list.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $agencies = Agency::where('status', Agency::STATUS['active'])->select(['id', 'name'])->get();

            return $this->sendResponse($agencies);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Agency list.
     *
     * @param \Illuminate\Http\Request  $request
     * @param int $agency_id
     * @return JsonResponse
     */
    public function checkdelete(Request $request, $agency_id): JsonResponse
    {
        try {
            $result = [
                'status' => 'deleteEnable'
            ];
            $agency = Agency::find($agency_id);
            if (!$agency) {
                throw new \Exception('Agency with ID=' . $agency_id . ' is not found', 404);
            }
            if (AgencyMember::where([
                'agency_id' => $agency_id,
            ])
                ->where('user_id', '<>', $agency->main_admin_id)
                ->first()) {
                $result['status'] = 'deleteDisable';
            }

            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Agency member list.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param int $agency_id
     * @return AgencyMemberCollection|JsonResponse
     */
    public function members(Request $request, $agency_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer',
                'filter' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            $rowPerPage = $request->per_page ?? config('view.row_per_page');

            $agency = Agency::find($agency_id);
            if (!$agency) {
                throw new \Exception('Agency with ID=' . $agency_id . ' is not found', 404);
            }

            $agencyMembersQuery = DB::table('agency_members')
                ->join('agencies', 'agency_members.agency_id', '=', 'agencies.id')
                ->join('users', 'agency_members.user_id', '=', 'users.id')
                ->where('agency_id', $agency->id)
                ->where('users.status', User::ACTIVE)
                ->where('agency_members.status', AgencyMember::STATUS['active']);
            if ($request->filter) {
                $agencyMembersQuery->where('first_name', 'like', '%' . $request->filter . '%')
                    ->orWhere('last_name', 'like', '%' . $request->filter . '%')
                    ->orWhere('users.name', 'like', '%' . $request->filter . '%')
                    ->orWhere('email', 'like', '%' . $request->filter . '%');
            }
            $agencyMembers = $agencyMembersQuery->orderByDesc('agency_members.created_at')->paginate($rowPerPage);

            return new AgencyMemberCollection($agencyMembers);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }
}
