Agency management module. app/Http/Api/V1/Controllers/AgencyController
Creation, editing, deletion of agencies, members list.
Each agency has an agency owner and members of agency.
When creating an agency, an owner (main_admin_id) is provided.
The assignment of members is in another module, users.


Payment module. app/Http/Api/V1/Controllers/PaymentController
Creation of subscription intent (subscribeIntent() method), subscription (subscribeIntent() method), subscription list (allSubscriptions method)

Integration API module app/Http/Api/V1/Controllers/IntegrationController
Connecting integrations, currently there is an option to connect Google search console api.
Getting statistics from GSC Api - method gscStat()
