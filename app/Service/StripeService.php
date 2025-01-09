<?php

namespace App\Service;

use App\Models\StripeProduct;
use App\Models\Subscription;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use App\Traits\ConsumesExternalServices;
use Illuminate\Support\Facades\Log;
use Session;
use Stripe\StripeClient;

class StripeService
{
    use ConsumesExternalServices;

    protected $baseUri;

    protected $key;

    protected $secret;

    protected $plans;

    public $stripe;

    public function __construct()
    {
        $this->baseUri = '';
        $this->key = '';
        $this->secret = '';

        $this->stripe = new StripeClient($this->secret);
    }

    public function resolveAuthorization(&$queryParams, &$formParams, &$headers)
    {
        $headers['Authorization'] = $this->resolveAccessToken();
    }

    public function decodeResponse($response)
    {
        return json_decode($response);
    }

    public function resolveAccessToken()
    {
        return "Bearer {$this->secret}";
    }

    public function handlePayment(Request $request)
    {
        $request->validate([
            'payment_method' => 'required',
        ]);
        $intent = $this->createIntent($request->value, $request->currency, $request->payment_method);
        Session::put('paymentIntentId', $intent->id);
    }

    public function handleApproval()
    {
        if (Session::has('paymentIntentId')) {
            $paymentIntentId = Session::get('paymentIntentId');
            $confirmation = $this->confirmPayment($paymentIntentId);
            // Check confirmation
            if ($confirmation->status === 'requires_action') {
                $clientSecret = $confirmation->client_secret;
                return [2, $clientSecret];

            }
            if ($confirmation->status === 'succeeded') {
                $name = $confirmation->charges->data[0]->billing_details->name;
                $currency = strtoupper($confirmation->currency);
                $amount = $confirmation->amount / $this->resolveFactor($currency);
                return [1,"Thanks, {$name}. We received your {$amount} {$currency} payment."];
            }
        }
        return [0,'We are unable to confirm your payment. Try again, please'];
    }

    public function subscribe(Request $request, $user = null)
    {
        if (is_null($user)) {
            $user = $request->user();
        }
        if (!($user->cus_id)) {
            $customer = $this->createCustomer(
                $user->name,
                $user->email
            );
        } else {
            $customer = $this->getCustomer($user->cus_id);
            if (empty($customer)) {
                $customer = $this->createCustomer(
                    $user->name,
                    $user->email
                );
            }
        }
        $customerId = $customer->id;
        $user->cus_id = $customerId;
        $user->save();

        $product = StripeProduct::where('product_stripe_id', $request->product_id)->first();

        $dbSubscription = Subscription::where('product_id', $product->product_stripe_id)
            ->where('user_id', $user->id)
            ->whereIn('stripe_status', [Subscription::STRIPE_active, Subscription::STRIPE_past_due])
            ->first();

        $price = $dbSubscription ? $product->next_payment : $product->first_payment;

        $data = [
            'customer' => $customerId,
            'items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product' => $product->product_stripe_id,
                        'unit_amount' => (int) $price * 100,
                        'recurring' => [
                            'interval' => $product->interval,
                        ]
                    ]
                ]
            ],
            'metadata' => [
                'nextChargeAmount' => $product->next_payment . ' usd',
                'productId' => $product->product_stripe_id,
                'paymentMethod' => $request->payment_method,
            ],
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types' => ['card', 'us_bank_account'],
            ],
            'default_payment_method' => $request->payment_method,
            'expand' => ['latest_invoice.payment_intent'],
            'cancel_at_period_end' => $product->trial == 1 ? 'true' : 'false',
        ];

        return $this->createSubscription($data);
    }

    public function upgrade($curSubscription, $product, $paymentMethod)
    {
        $data = [
            'items' => [
                [
                    'id' => $curSubscription->subscription_item_id,
                    'price_data' => [
                        'currency' => 'usd',
                        'product' => $product->product_stripe_id,
                        'unit_amount' => (int) $product->next_payment * 100,
                        'recurring' => [
                            'interval' => $product->interval
                        ]
                    ]
                ],
            ],
            'metadata' => [
                'nextChargeAmount' => $product->next_payment . ' usd',
                'productId' => $product->product_stripe_id,
                'paymentMethod' => $paymentMethod,
            ],
            'proration_behavior' => 'always_invoice',
            'cancel_at_period_end' => 'false',
        ];

        if ($curSubscription->current_period_end > time()) {
            $data['trial_end'] = $curSubscription->current_period_end;
        }

        return $this->updateSubscription($curSubscription->subscription_id, $data);
    }

    public function enableCustomerSession(Request $request, $user = null)
    {
        if (is_null($user)) {
            $user = $request->user();
        }
        if (!($user->cus_id)) {
            $customer = $this->createCustomer(
                $user->name,
                $user->email
            );
        } else {
            $customer = $this->getCustomer($user->cus_id);
            if (empty($customer)) {
                $customer = $this->createCustomer(
                    $user->name,
                    $user->email
                );
            }
        }
        $customerId = $customer->id;
        $user->cus_id = $customerId;
        $user->save();

        $customer_session = $this->stripe->customerSessions->create([
            'customer' => $customerId,
                'components' => [
                'payment_element' => [
                    'enabled' => true,
                    'features' => [
                        'payment_method_redisplay' => 'enabled',
                        'payment_method_save' => 'enabled',
                        'payment_method_save_usage' => 'on_session',
                        'payment_method_remove' => 'enabled',
                    ],
                ],
            ],
        ]);

        return $customer_session->client_secret;
    }

    public function createDbSubscription(Request $request, $user = null) {
        if (is_null($user)) {
            $user = $request->user();
        }
        $product = StripeProduct::where('product_stripe_id', $request->product_id)->first();
        $dbSubscription = Subscription::where('product_id', $product->product_stripe_id)
            ->where('user_id', $user->id)
            ->whereIn('stripe_status', [Subscription::STRIPE_active, Subscription::STRIPE_past_due])
            ->first();

        if (!$dbSubscription) {

            $subscription = $this->getSubscriptions($request->subscription_id);

            $data = [
                'cancel_at_period_end' => 'false',
                'proration_behavior' => 'create_prorations',
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price_data' => [
                            'currency' => 'usd',
                            'product' => $product->product_stripe_id,
                            'unit_amount' => (int)$product->next_payment * 100,
                            'recurring' => [
                                'interval' => $product->interval
                            ]
                        ]
                    ]
                ],
            ];

            $this->updateSubscription($subscription->id, $data);

            Subscription::createSubscription($subscription, $user->id);
        }
    }


    public function createIntent($value, $currency, $paymentMethod)
    {
        return $this->makeRequest(
            'POST',
            '/v1/payment_intents',
            [],
            [
                'amount' => round($value * $this->resolveFactor($currency)),
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethod,
                'confirmation_method' => 'manual',
            ],
        );
    }

    public function confirmPayment($paymentIntentId)
    {
        return $this->makeRequest(
            'POST',
            "/v1/payment_intents/{$paymentIntentId}/confirm",
            [],
            [
                'return_url' => 'https://www.example.com',
            ]
        );
    }

    public function createCustomer($name, $email)
    {
        return $this->makeRequest(
            'POST',
            '/v1/customers',
            [],
            [
                'name' => $name,
                'email' => $email
            ],
        );
    }

    public function getCustomer($customerId) {
        try {
            $customer = $this->makeRequest('GET', "/v1/customers/$customerId");
            if (isset($customer->deleted) and $customer->deleted ) {
                $customer = null;
            }
        }
        catch (\Exception $e) {
            $customer = null;
        }
        return $customer;
    }

    public function getCustomerPaymentMethods($customerId) {
        $methods = $this->makeRequest(
            'GET',
            "/v1/payment_methods",
            [],
            [
                'customer' => $customerId,
                'type' => 'card'
            ]
        );
        return $methods->data;
    }

    public function getPaymentMethodConfiguration($paymentMethodConfId) {
        return $this->stripe->paymentMethodConfigurations->retrieve($paymentMethodConfId, []);
    }

    public function getCustomersPaymentMethod($customerId, $paymentMethodId) {
        $methods = $this->makeRequest(
            'GET',
            "/v1/customers/$customerId/payment_methods/$paymentMethodId",
            [],
            []
        );
        return $methods;
    }
    public function getPaymentMethod($paymentMethodId) {
        $methods = $this->makeRequest(
            'GET',
            "/v1/payment_methods/$paymentMethodId",
            [],
            []
        );
        return $methods;
    }

    public function attachPaymentMethod($customerId, $paymentMethodId) {
        $result = $this->makeRequest(
            'POST',
            "/v1/payment_methods/$paymentMethodId/attach",
            [],
            [
                'customer' => $customerId,
            ]
        );
        return $result;
    }

    public function detachPaymentMethod($paymentMethodId) {
        $result = $this->makeRequest(
            'POST',
            "/v1/payment_methods/$paymentMethodId/detach",
            [],
            []
        );
        return $result;
    }

    public function updatePaymentMethod($paymentMethodId, $data)
    {
        $address = [];
        if (isset($data['city'])) {
            $address['city'] = $data['city'];
        }
        if (isset($data['country'])) {
            $address['country'] = $data['country'];
        }
        if (isset($data['postal_code'])) {
            $address['postal_code'] = $data['postal_code'];
        }
        if (isset($data['state'])) {
            $address['state'] = $data['state'];
        }
        if (isset($data['billing_address_1'])) {
            $address['line1'] = $data['billing_address_1'];
        }
        if (isset($data['billing_address_2'])) {
            $address['line2'] = $data['billing_address_2'];
        }

        $result = $this->makeRequest(
            'POST',
            "/v1/payment_methods/$paymentMethodId",
            [],
            [
                'billing_details' => [
                    'address' => $address,
                    'name' => $data['name'],
                    'email' => $data['email']
                ]
            ]
        );
        return $result;
    }
    public function createSubscription($data) {
        $result  = $this->makeRequest(
            'POST',
            '/v1/subscriptions',
            [],
            $data,
        );

        return $result;
    }
    public function updateSubscription($subscriptionId, $data)
    {
        return $this->makeRequest(
            'POST',
            "/v1/subscriptions/$subscriptionId",
            [],
            $data,
        );
    }
    public function cancelSubscription($subscriptionId)
    {
        return $this->makeRequest(
            'DELETE',
            "/v1/subscriptions/$subscriptionId",
            [],
            [],
        );
    }
    public function createProduct($productName) {
        $result = $this->makeRequest(
            'POST',
            '/v1/products',
            [],
            [
                'name' => $productName,
            ],
        );
        return $result;
    }
    public function updateProduct($productId, $data) {
        $result = $this->makeRequest(
            'POST',
            "/v1/products/$productId",
            [],
            $data,
        );
        return $result;
    }
    public function getProducts($id=null) {
        if ($id) {
            $result = $this->makeRequest(
                'GET',
                "/v1/products/$id",
                [],
                []
            );
        }
        else {
            $result = $this->makeRequest(
                'GET',
                "/v1/products",
                [],
                []
            );

            $result = $result->data;
        }
        return $result;
    }

    public function deleteProduct($productId) {
        $result = $this->makeRequest(
            'DELETE',
            "/v1/products/$productId",
            [],
            [],
        );
        return $result;
    }
    public function getPrices($id=null) {
        if ($id) {
            $result = $this->makeRequest(
                'GET',
                "/v1/prices/$id",
                [],
                []
            );
            $result->product_name = $this->getProductName($result->product);
        }
        else {
            $result = $this->makeRequest(
                'GET',
                "/v1/prices",
                [],
                []
            );
            $result = $result->data;
            foreach ($result as &$item) {
                $item->product_name = $this->getProductName($item->product);
            }
        }
        return $result;
    }
    public function getSubscriptions($id=null) {
        if ($id) {
            $result = $this->makeRequest(
                'GET',
                "/v1/subscriptions/$id",
                [],
                []
            );
        }
        else {
            $result = $this->makeRequest(
                'GET',
                "/v1/subscriptions",
                [],
                []
            );
            $result = $result->data;
            foreach ($result as &$subscription) {
                $items = $subscription->items->data;
                $products = [];
                $prices = [];
                $interval = [];
                foreach ($items as $item) {
                    //$products[] = $this->getProductName($item->plan->product);
                    $prices[] = $item->plan->amount / 100 . ' ' . $item->plan->currency;
                    $interval[] = $item->plan->interval;
                }
                $subscription->product_names = implode("<br/>", $products);
                $subscription->product_prices = implode("<br/>", $prices);
                $subscription->product_intervals = implode("<br/>", $interval);
                $subscription->customer_email = $this->getCustomerName($subscription->customer);
            }
        }
        return $result;
    }
    public function getCustomerSubscription($id, $status = 'all') {
        $result = $this->makeRequest(
            'GET',
            "/v1/subscriptions",
            [],
            [
                'customer' => $id,
                'status' => $status
            ]
        );

        return $result;
    }
    public function getCustomers($id=null) {
        if ($id) {
            $result = $this->makeRequest(
                'GET',
                "/v1/customers/$id",
                [],
                []
            );
        }
        else {
            $result = $this->makeRequest(
                'GET',
                "/v1/customers",
                [],
                []
            );
            $result = $result->data;
        }
        return $result;
    }

    public function editSubscription($subscriptionId) {
        $subscription = $this->getSubscriptions($subscriptionId);
    }
    public function updateSubscriptionData($subscriptionId, $data) {
        $result = $this->makeRequest(
            'POST',
            "/v1/subscriptions/$subscriptionId",
            [],
            [
                'items' => [
                    ['price_data' =>
                        [
                            'currency' => $data['currency'],
                            'product' => $data['product'],
                            'recurring' => [
                                'interval' => $data['interval'],
                            ],
                            'unit_amount_decimal' => $data['price'] * 100,
                        ]
                    ]
                ]

            ],
        );
        return $result;
    }
    public function getProductName($productId) {
        $product = $this->getProducts($productId);
        return $product->name;
    }
    public function getCustomerName($customerId) {
        $customer = $this->getCustomers($customerId);
        return $customer->email;
    }
    public function deleteSubscriptionItem($priceId) {
        try {
            $result = $this->makeRequest(
                'DELETE',
                "/v1/subscription_items/$priceId",
                [],
                []
            );
        }
        catch (\Exception $e) {
            return 'This Subscription Item can not delete';
        }

        return $result;
    }
    public function updateSubscriptionItem($subscriptionItemIdId, $data) {
        $result = $this->makeRequest(
            'POST',
            "/v1/subscription_items/$subscriptionItemIdId",
            [],
            $data
        );
        return $result;
    }
    public function resolveFactor($currency)
    {
        $zeroDecimalCurrencies = ['JPY'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return 1;
        }

        return 100;
    }

    public function getProductPrices($product) {
        $result = $this->makeRequest(
            'GET',
            "/v1/prices",
            [],
            [
                'product' => $product
            ]
        );
        return $result->data;
    }
    public function changeStatusPlan($priceId, $action) {
        $result = $this->makeRequest(
            'POST',
            "/v1/plans/$priceId",
            [],
            [
                'active' => $action
            ],
        );
        return $result;
    }
    public function changeTrialDays($priceId, $trialdays) {
        $result = $this->makeRequest(
            'POST',
            "/v1/plans/$priceId",
            [],
            [
                'trial_period_days' => $trialdays
            ],
        );
        return $result;
    }
    public function addPlan($data) {
        /*dd($data);*/
        $result = $this->makeRequest(
            'POST',
            "/v1/plans",
            [],
            $data,
        );
        return $result;
    }
    public function addPrice($data) {
        /*dd($data);*/
        $result = $this->makeRequest(
            'POST',
            "/v1/prices",
            [],
            $data,
        );
        return $result;
    }
    public function deletePrice($priceId) {

        $result = $this->makeRequest(
            'DELETE',
            "/v1/prices/$priceId",
            [],
            [],
        );
        return $result;
    }
    public function deletePlan($id) {
        try {
            $result = $this->makeRequest(
                'DELETE',
                "/v1/plans/$id",
                [],
                []
            );
        }
        catch (\Exception $e) {
            return 'This Subscription Item can not delete';
        }
        return $result;
    }
    public function getSubscriptionStatus($subscriptionId) {
        if (!$subscriptionId) {
            return false;
        }
        $subscription = $this->getSubscriptions($subscriptionId);
        if ($subscription) {
            return $subscription->status;
        }
        return false;
    }

    public function createPaymentIntent($items, $customerId) {
        // Create a PaymentIntent with amount and currency
        $paymentIntent = $this->stripe->paymentIntents->create([
            'customer' => $customerId,
            'amount' => self::calculateOrderAmount($items),
            'currency' => 'usd',
            // In the latest version of the API, specifying the `automatic_payment_methods` parameter is optional because Stripe enables its functionality by default.
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        return $paymentIntent;
    }
}
