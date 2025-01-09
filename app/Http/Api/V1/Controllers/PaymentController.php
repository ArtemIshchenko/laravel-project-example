<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Resources\SubscriptionCollection;
use App\Models\StripeProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Service\StripeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private $paymentPlatform;

    public function __construct(StripeService $paymentStripe)
    {
        $this->paymentPlatform = $paymentStripe;
    }


    /**
     * stripe subscribe intent
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribeIntent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|string:255',
                'from_agency' => 'integer|nullable',
                'save_payment_method' => 'boolean|nullable',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode('; ', $validator->errors()->all()));
            }

            if ($request->from_agency) {
                $user = User::find($request->from_agency);
            } else {
                $user = Auth::user();
            }

            if (Subscription::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->whereIn('stripe_status', [Subscription::STRIPE_active, Subscription::STRIPE_trialing])
                ->exists()) {
                throw new \Exception('The subscription with this product already exists');
            }

            $selectProduct = StripeProduct::where('product_stripe_id', $request->product_id)
                ->where('user_id', 0)
                ->first();
            if (!$selectProduct) {
                throw new \Exception('Selected product is not exists');
            }

            $response = $this->paymentPlatform->stripe->setupIntents->create([
                'customer' => $user->cus_id,
                'payment_method_types' => ['card', 'us_bank_account'],
                'metadata' => [
                    'product_id' => $request->product_id,
                    'save_payment_method' => $request->save_payment_method ?? false,
                ],
            ]);

            if (!$response) {
                throw new \Exception('Somthing went wrong');
            }

            if (!($user->cus_id)) {
                $customer = $this->paymentPlatform->createCustomer(
                    $user->name,
                    $user->email
                );
            } else {
                $customer = $this->paymentPlatform->getCustomer($user->cus_id);
                if (empty($customer)) {
                    $customer = $this->paymentPlatform->createCustomer(
                        $user->name,
                        $user->email
                    );
                }
            }
            $user->cus_id = $customer->id;
            $user->save();

            $paymentMethods = UserPaymentMethod::where('user_id', $user->id)
                ->where('status', UserPaymentMethod::STATUS['connected'])
                ->select(['method_type', 'method_id', 'last_four_digits'])
                ->orderByDesc('created_at')
                ->get();

            $result = [
                'client_secret' => $response->client_secret,
                'other_payment_methods' => $paymentMethods
            ];

            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * stripe subscribe confirm
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string:255',
                'product_id' => 'required|string:255',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode('; ', $validator->errors()->all()));
            }

            if ($request->from_agency) {
                $user = User::find($request->from_agency);
            } else {
                $user = Auth::user();
            }

            $paymentMethodModel = UserPaymentMethod::where('user_id', $user->id)
                ->where('method_id', $request->payment_method)
                ->first();
            if (!$paymentMethodModel) {
                throw new \Exception('The payment method ' . $request->payment_method . ' is not found');
            }

            $processed = Subscription::process($request->product_id, $request->payment_method, $user, $request);
            if (!$processed) {
                throw new \Exception('It is not possible to process the subscription');
            }

            return $this->sendResponse([], 'You successful subcribed to the product ' . $request->product_id);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * stripe enable customer session
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function enCustomerSession(Request $request): JsonResponse
    {
        try {
            $result = [
                'customer_session_client_secret' => $this->paymentPlatform->enableCustomerSession($request),
            ];

            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

    /**
     * stripe enable customer session
     *
     * @return SubscriptionCollection|JsonResponse
     */
    public function allSubscriptions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer',
                'status' => 'nullable|in:' . implode(',', array_keys(Subscription::getStatuses())),
                'filter' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                throw new \Exception(implode(' ', $validator->errors()->all()));
            }
            $rowPerPage = $request->per_page ?? config('view.row_per_page');
            $query = Subscription::orderByDesc('created_at');
            if ($request->status) {
                $query->where('stripe_status', $request->status);
            }
            if ($request->filter) {
                $query
                    ->whereHas('user', function (Builder $q) use ($request) {
                        $q->where('email', 'like', '%' . $request->filter . '%');
                    })
                    ->orWhereHas('product', function (Builder $q) use ($request) {
                        $q->where('product_stripe_name', 'like', '%' . $request->filter . '%');
                    });
            }
            $subscriptions = $query->paginate($rowPerPage);

            return new SubscriptionCollection($subscriptions);
        } catch (\Exception $e) {
            Log::info('Error: ' . $e->getMessage() . '; File: ' . $e->getFile() . '; Line: ' . $e->getLine());
            return $this->sendError('Error: ' . $e->getMessage());
        }
    }

}